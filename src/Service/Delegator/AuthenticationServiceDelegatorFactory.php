<?php declare(strict_types=1);

namespace Common\Service\Delegator;

use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Psr\Container\ContainerInterface;

/**
 * Avoid to start a session for stateless requests of anonymous visitors.
 *
 * Omeka reads the identity during the bootstrap, so a session is started for
 * each request, with a cookie, a row in the database and headers that disable
 * the cache. A visitor without session cookie has no identity, so the identity
 * is not stored for the paths listed in the config key "session_free_paths" of
 * the module Common, for example the files checked by a module.
 *
 * The service is built here, because the session storage starts the session as
 * soon as it is created. The core service is kept during install, migration and
 * for requests authenticated by an api key.
 */
class AuthenticationServiceDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, ?array $options = null
    ) {
        if (!$this->isSessionFree($container) || !$this->isStandardRequest($container)) {
            return $callback();
        }
        $userRepository = $container->get('Omeka\EntityManager')->getRepository(\Omeka\Entity\User::class);
        return new AuthenticationService(
            new DoctrineWrapper(new NonPersistent(), $userRepository),
            new PasswordAdapter($userRepository)
        );
    }

    /**
     * Check if the request path starts with a session free path and if there is
     * no session cookie.
     */
    protected function isSessionFree(ContainerInterface $container): bool
    {
        $config = $container->get('Config');
        $paths = $config['common']['session_free_paths'] ?? [];
        if (!$paths) {
            return false;
        }

        // Use the same session name than the core, because the session may be
        // not configured yet.
        // @see \Omeka\Mvc\MvcListeners::bootstrapSession()
        $sessionName = $config['session']['config']['name'] ?? null ?: md5(OMEKA_PATH);
        if (!empty($_COOKIE[$sessionName])) {
            return false;
        }

        $requestPath = $this->requestPath();
        if ($requestPath === '') {
            return false;
        }

        foreach ($paths as $path) {
            $path = '/' . ltrim((string) $path, '/');
            if ($path !== '/' && strpos($requestPath, $path) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the request is a standard one, like in the core factory.
     *
     * @see \Omeka\Service\AuthenticationServiceFactory
     */
    protected function isStandardRequest(ContainerInterface $container): bool
    {
        /** @var \Omeka\Mvc\Status $status */
        $status = $container->get('Omeka\Status');
        return $status->isInstalled()
            && !($status->needsVersionUpdate() && $status->needsMigration())
            && !$status->isKeyauthRequest();
    }

    /**
     * Get the path of the request, relative to the base path of Omeka.
     */
    protected function requestPath(): string
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path === '') {
            return '';
        }
        // The base path is the directory of the front controller "index.php".
        $basePath = rtrim(strtr(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), ['\\' => '/']), '/');
        if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
            $path = substr($path, strlen($basePath));
        }
        return $path;
    }
}
