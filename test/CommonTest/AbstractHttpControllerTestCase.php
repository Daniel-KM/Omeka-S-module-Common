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
use Omeka\Mvc\Application;

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

        $appConfig = $this->applicationConfig;
        $this->application = Application::init($appConfig);

        $events = $this->application->getEventManager();
        $this->application->getServiceManager()->get('SendResponseListener')->detach($events);

        return $this->application;
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
    public function dispatch($url, $method = null, $params = [], $isXmlHttpRequest = false)
    {
        // Reset application to get clean state.
        $this->reset();

        // Ensure application is initialized.
        $this->getApplication();

        // Re-authenticate if login is required.
        if ($this->requiresLogin) {
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

        if (self::$adminUser) {
            // Set identity directly in storage (bypasses adapter).
            $auth->getStorage()->write(self::$adminUser);
        }
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
    public function dispatchUnauthenticated($url, $method = null, $params = [], $isXmlHttpRequest = false)
    {
        // Temporarily disable login requirement.
        $originalRequiresLogin = $this->requiresLogin;
        $this->requiresLogin = false;

        // Reset and dispatch.
        $this->reset();
        $this->getApplication();
        $this->logout();

        parent::dispatch($url, $method, $params, $isXmlHttpRequest);

        // Restore login requirement.
        $this->requiresLogin = $originalRequiresLogin;
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
