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
 * soon as it is created.
 */
class AuthenticationServiceDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, ?array $options = null
    ) {
        if (!$this->isSessionFree($container)) {
            return $callback();
        }
        $userRepository = $container->get('Omeka\EntityManager')->getRepository(\Omeka\Entity\User::class);
        return new AuthenticationService(
            new DoctrineWrapper(new NonPersistent(), $userRepository),
            new PasswordAdapter($userRepository)
        );
    }

    protected function isSessionFree(ContainerInterface $container): bool
    {
        $paths = $container->get('Config')['common']['session_free_paths'] ?? [];
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!$paths
            || $requestPath === ''
            || !empty($_COOKIE[session_name()])
        ) {
            return false;
        }
        foreach ($paths as $path) {
            if (strpos($requestPath, $path) !== false) {
                return true;
            }
        }
        return false;
    }
}
