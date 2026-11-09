<?php declare(strict_types=1);

/**
 * Enhanced HTTP controller test case with authentication helpers.
 *
 * This class solves the common problem where authentication doesn't persist
 * across `reset()` calls in Laminas controller tests. When a test calls
 * `dispatch()`, Laminas resets the application, which clears the identity.
 *
 * The solution is to re-authenticate before each dispatch by setting the
 * identity directly in the auth storage (bypassing the adapter).
 *
 * Usage:
 * ```php
 * class MyControllerTest extends \Common\Test\AbstractHttpControllerTestCase
 * {
 *     public function testIndex(): void
 *     {
 *         // Authentication is automatic
 *         $this->dispatch('/admin/my-module');
 *         $this->assertResponseStatusCode(200);
 *     }
 *
 *     public function testAsAnotherUser(): void
 *     {
 *         // Kept for the next dispatches, until logout().
 *         $this->loginAs('editor@example.com');
 *         $this->dispatch('/admin/my-module');
 *     }
 *
 *     public function testRestApi(): void
 *     {
 *         // The rest api ignores the session: a temporary key is used.
 *         $this->dispatchApi('/api/items');
 *         $this->assertResponseStatusCode(200);
 *     }
 *
 *     public function testRequiresLogin(): void
 *     {
 *         // Test without authentication
 *         $this->dispatchUnauthenticated('/admin/my-module');
 *         $this->assertResponseStatusCode(302); // Redirect to login
 *     }
 * }
 * ```
 *
 * @see https://github.com/omeka/omeka-s/pull/2411
 *
 * @copyright Daniel Berthereau, 2017-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace CommonTest;

use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase as LaminasAbstractHttpControllerTestCase;
use Omeka\Entity\User;

abstract class AbstractHttpControllerTestCase extends LaminasAbstractHttpControllerTestCase
{
    /**
     * Whether admin login is required for tests.
     *
     * Set to false in tests that should run without authentication.
     */
    protected bool $requiresLogin = true;

    /**
     * Cached admin user entity.
     *
     * Static to avoid repeated database lookups across tests.
     */
    protected static ?User $adminUser = null;

    /**
     * Admin email used for authentication.
     *
     * Override in subclass if using different admin credentials.
     */
    protected string $adminEmail = 'admin@example.com';

    /**
     * Id of the user to dispatch as instead of the admin, until logout().
     */
    protected ?int $dispatchUserId = null;

    /**
     * Url and method of the next dispatch, set in the request before bootstrap.
     */
    protected ?array $dispatchRequest = null;

    public function setUp(): void
    {
        $config = require OMEKA_PATH . '/application/config/application.config.php';
        $reader = new \Laminas\Config\Reader\Ini;
        $testConfig = [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini'),
        ];
        $config = array_merge($config, $testConfig);
        $this->setApplicationConfig($config);

        parent::setUp();
    }

    /**
     * Get the application instance.
     *
     * Uses Omeka\Mvc\Application instead of Laminas default.
     */
    public function getApplication()
    {
        if ($this->application) {
            return $this->application;
        }

        $this->application = $this->initApplication($this->applicationConfig);

        $events = $this->application->getEventManager();
        $this->application->getServiceManager()->get('SendResponseListener')->detach($events);

        return $this->application;
    }

    /**
     * Init the application like Omeka\Mvc\Application::init().
     *
     * The url of the dispatch is set in the request before the bootstrap,
     * because some modules check the route during the bootstrap (admin or site
     * request, via the service Omeka\Status that caches the result). Else the
     * request is empty and an admin page is managed as a public one.
     */
    protected function initApplication(array $configuration): \Laminas\Mvc\Application
    {
        $smConfig = new \Laminas\Mvc\Service\ServiceManagerConfig($configuration['service_manager'] ?? []);
        $serviceManager = new \Laminas\ServiceManager\ServiceManager();
        $smConfig->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $configuration);

        $activeModules = $serviceManager->get('Omeka\ModuleManager')
            ->getModulesByState(\Omeka\Module\Manager::STATE_ACTIVE);
        $moduleManager = $serviceManager->get('ModuleManager');
        $moduleManager->setModules(array_merge($configuration['modules'], array_keys($activeModules)));
        $moduleManager->loadModules();

        if ($this->dispatchRequest) {
            $uri = new \Laminas\Uri\Http($this->dispatchRequest['url']);
            /** @var \Laminas\Http\PhpEnvironment\Request $request */
            $request = $serviceManager->get('Request');
            $request
                ->setMethod($this->dispatchRequest['method'] ?: 'GET')
                ->setUri($uri)
                ->setRequestUri($uri->getPath());
        }

        $config = $serviceManager->get('Config');
        $listeners = array_unique(array_merge($config['listeners'] ?? [], $configuration['listeners'] ?? []));
        return $serviceManager->get('Application')->bootstrap($listeners);
    }

    /**
     * Close the database connection before the application is dropped.
     *
     * Each application has its own connection, that is never closed, so a
     * long suite exhausts the connections of the server (error 1040).
     */
    public function reset($keepPersistence = false)
    {
        if ($this->application) {
            $services = $this->application->getServiceManager();
            if ($services->has('Omeka\Connection')) {
                $services->get('Omeka\Connection')->close();
            }
        }
        return parent::reset($keepPersistence);
    }

    /**
     * Dispatch the MVC with a URL.
     *
     * Overridden to ensure authentication before dispatch (after reset).
     *
     * @param string $url Request URL.
     * @param string|null $method HTTP method (GET, POST, etc.).
     * @param array $params Request parameters.
     * @param bool $isXmlHttpRequest Whether this is an AJAX request.
     */
    public function dispatch($url, $method = null, $params = [], $isXmlHttpRequest = false): void
    {
        // Reset application to get clean state.
        $this->reset();

        // Ensure application is initialized, with the url of the request.
        $this->dispatchRequest = ['url' => $url, 'method' => $method];
        try {
            $this->getApplication();
        } finally {
            $this->dispatchRequest = null;
        }

        // Re-authenticate: the reset cleared the identity.
        if ($this->dispatchUserId) {
            $this->writeIdentity($this->dispatchUserId);
        } elseif ($this->requiresLogin) {
            $this->loginAsAdmin();
        }

        parent::dispatch($url, $method, $params, $isXmlHttpRequest);
    }

    /**
     * Login as admin user on the current application instance.
     *
     * Sets the identity directly in storage to persist across resets.
     * This bypasses the authentication adapter for speed.
     */
    protected function loginAsAdmin(): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        // Skip if already authenticated.
        if ($auth->hasIdentity()) {
            return;
        }

        // Get or cache the admin user.
        if (self::$adminUser === null) {
            $em = $services->get('Omeka\EntityManager');
            self::$adminUser = $em->getRepository(User::class)
                ->findOneBy(['email' => $this->adminEmail]);
        }

        // Only the id of the cached user is reused: the entity belongs to the
        // entity manager of a previous application, so writing it as identity
        // would make Doctrine see a new user when an entity owned by the
        // current user is flushed.
        if (self::$adminUser) {
            $this->writeIdentity(self::$adminUser->getId());
        }
    }

    /**
     * Dispatch the next requests as another user than the admin.
     *
     * Each dispatch resets the application, so the identity is written again
     * before each of them, until logout().
     *
     * @param User|int|string $user The user, its id or its email.
     */
    protected function loginAs($user): void
    {
        if ($user instanceof User) {
            $userId = $user->getId();
        } elseif (is_int($user)) {
            $userId = $user;
        } else {
            $entity = $this->getEntityManager()->getRepository(User::class)
                ->findOneBy(['email' => (string) $user]);
            $userId = $entity ? $entity->getId() : null;
        }
        if (!$userId) {
            throw new \InvalidArgumentException('Unknown user to log in.');
        }
        $this->dispatchUserId = $userId;
        $this->writeIdentity($userId);
    }

    /**
     * Write a user as identity of the current application, without adapter.
     */
    protected function writeIdentity(int $userId): void
    {
        $services = $this->getApplicationServiceLocator();
        $user = $services->get('Omeka\EntityManager')->find(User::class, $userId);
        $services->get('Omeka\AuthenticationService')->getStorage()->write($user);
        // Normally done during bootstrap, before the identity is set in tests.
        $services->get('Omeka\Settings\User')->setTargetId($userId);
    }

    /**
     * Login using the authentication adapter (slower but more realistic).
     *
     * Use this when you need to test actual authentication flow.
     *
     * @param string $email User email.
     * @param string $password User password.
     * @return bool True if authentication succeeded.
     */
    protected function loginWithCredentials(string $email, string $password): bool
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);

        $result = $auth->authenticate();
        return $result->isValid();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
        $this->dispatchUserId = null;
    }

    /**
     * Run a dispatch without authentication.
     *
     * Useful for testing routes that should redirect to login.
     *
     * @param string $url Request URL.
     * @param string|null $method HTTP method.
     * @param array $params Request parameters.
     * @param bool $isXmlHttpRequest Whether this is an AJAX request.
     */
    public function dispatchUnauthenticated($url, $method = null, $params = [], $isXmlHttpRequest = false): void
    {
        // Temporarily disable login requirement.
        $originalRequiresLogin = $this->requiresLogin;
        $originalUserId = $this->dispatchUserId;
        $this->requiresLogin = false;

        // Reset and dispatch.
        $this->reset();
        $this->getApplication();
        $this->logout();

        parent::dispatch($url, $method, $params, $isXmlHttpRequest);

        // Restore login requirement.
        $this->requiresLogin = $originalRequiresLogin;
        $this->dispatchUserId = $originalUserId;
    }

    /**
     * Run a dispatch on the rest api, authenticated with a temporary api key.
     *
     * The rest api (/api) authenticates by key only and ignores the identity
     * of the session, so a dispatch() is anonymous there. Use /api-local for a
     * request authenticated by the session.
     *
     * @param User|int|string $user The user, its id or its email.
     */
    public function dispatchApi(string $url, ?string $method = null, array $params = [], $user = 'admin@example.com'): void
    {
        $entityManager = $this->getEntityManager();
        $owner = $user instanceof User || is_int($user)
            ? $entityManager->find(User::class, $user instanceof User ? $user->getId() : $user)
            : $entityManager->getRepository(User::class)->findOneBy(['email' => (string) $user]);
        if (!$owner) {
            throw new \InvalidArgumentException('Unknown user for the api key.');
        }
        $apiKey = new \Omeka\Entity\ApiKey();
        $apiKey->setId();
        $apiKey->setLabel('Test');
        $apiKey->setOwner($owner);
        $credential = $apiKey->setCredential();
        $entityManager->persist($apiKey);
        $entityManager->flush();
        $keyId = $apiKey->getId();

        // The key adapter stores the ip of the client, missing in cli.
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $url .= (strpos($url, '?') === false ? '?' : '&')
            . http_build_query(['key_identity' => $keyId, 'key_credential' => $credential]);
        try {
            $this->dispatch($url, $method, $params);
        } finally {
            if ($remoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            }
            $entityManager = $this->getEntityManager();
            $apiKey = $entityManager->find(\Omeka\Entity\ApiKey::class, $keyId);
            if ($apiKey) {
                $entityManager->remove($apiKey);
                $entityManager->flush();
            }
        }
    }

    /**
     * Get the API manager for making API calls in tests.
     *
     * @return \Omeka\Api\Manager
     */
    protected function api()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the Entity Manager for direct database access in tests.
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
    }
}
