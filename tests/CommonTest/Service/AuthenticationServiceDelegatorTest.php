<?php declare(strict_types=1);

namespace CommonTest\Service;

use Common\Service\Delegator\AuthenticationServiceDelegatorFactory;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Chain;
use Omeka\Authentication\Storage\DoctrineWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AuthenticationServiceDelegatorTest extends TestCase
{
    protected array $server;

    protected array $cookie;

    public function setUp(): void
    {
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;
        $_SERVER['SCRIPT_NAME'] = '/omeka/index.php';
        unset($_COOKIE[md5(OMEKA_PATH)], $_COOKIE[session_name()], $_COOKIE['omeka_session']);
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server;
        $_COOKIE = $this->cookie;
    }

    /**
     * Get the class of the storage of the authentication service.
     *
     * The core service is simulated with a storage "Chain".
     */
    protected function storage(string $uri, array $options = []): string
    {
        $options += [
            'paths' => ['/access/files/'],
            'session_name' => null,
            'installed' => true,
            'keyauth' => false,
        ];
        $_SERVER['REQUEST_URI'] = $uri;

        $config = ['common' => ['session_free_paths' => $options['paths']]];
        if ($options['session_name']) {
            $config['session']['config']['name'] = $options['session_name'];
        }

        $status = $this->createMock(\Omeka\Mvc\Status::class);
        $status->method('isInstalled')->willReturn($options['installed']);
        $status->method('needsVersionUpdate')->willReturn(false);
        $status->method('isKeyauthRequest')->willReturn($options['keyauth']);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $container = new class(['Config' => $config, 'Omeka\Status' => $status, 'Omeka\EntityManager' => $entityManager]) implements ContainerInterface {
            private array $services;

            public function __construct(array $services)
            {
                $this->services = $services;
            }

            public function get($id)
            {
                return $this->services[$id];
            }

            public function has($id): bool
            {
                return isset($this->services[$id]);
            }
        };

        $auth = (new AuthenticationServiceDelegatorFactory())(
            $container,
            'Omeka\AuthenticationService',
            fn () => new AuthenticationService(new Chain())
        );
        return get_class($auth->getStorage());
    }

    public function testStatelessPathWithoutCookieIsNotStored(): void
    {
        $this->assertSame(DoctrineWrapper::class, $this->storage('/omeka/access/files/original/a.jpg?x=1'));
    }

    public function testStatelessPathAtRootIsNotStored(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $this->assertSame(DoctrineWrapper::class, $this->storage('/access/files/original/a.jpg'));
    }

    public function testStatelessPathWithCookieKeepsTheSession(): void
    {
        // The default session name of Omeka.
        $_COOKIE[md5(OMEKA_PATH)] = 'abc';
        $this->assertSame(Chain::class, $this->storage('/omeka/access/files/original/a.jpg'));
    }

    public function testCookieWithTheDefaultNameOfPhpIsNotTheSession(): void
    {
        $_COOKIE[session_name()] = 'abc';
        $this->assertSame(DoctrineWrapper::class, $this->storage('/omeka/access/files/original/a.jpg'));
    }

    public function testCookieWithTheSessionNameOfTheConfigKeepsTheSession(): void
    {
        $_COOKIE['omeka_session'] = 'abc';
        $this->assertSame(Chain::class, $this->storage('/omeka/access/files/original/a.jpg', ['session_name' => 'omeka_session']));

        // A cookie with the default name is not the session of Omeka.
        unset($_COOKIE['omeka_session']);
        $_COOKIE[md5(OMEKA_PATH)] = 'abc';
        $this->assertSame(DoctrineWrapper::class, $this->storage('/omeka/access/files/original/a.jpg', ['session_name' => 'omeka_session']));
    }

    public function testPathIsCheckedFromTheStart(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/s/site/page/access/files/original/a.jpg'));
        $this->assertSame(Chain::class, $this->storage('/other/access/files/original/a.jpg'));
    }

    public function testOtherPathKeepsTheSession(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/s/site/item/1'));
    }

    public function testNoPathKeepsTheSession(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/access/files/original/a.jpg', ['paths' => []]));
    }

    public function testRootPathIsIgnored(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/s/site/item/1', ['paths' => ['/', '']]));
    }

    public function testCoreServiceIsKeptDuringInstall(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/access/files/original/a.jpg', ['installed' => false]));
    }

    public function testCoreServiceIsKeptForKeyAuthentication(): void
    {
        $this->assertSame(Chain::class, $this->storage('/omeka/access/files/original/a.jpg', ['keyauth' => true]));
    }
}
