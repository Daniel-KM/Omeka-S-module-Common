<?php declare(strict_types=1);

namespace CommonTest;

class UpgradeTrustedProxiesTest extends AbstractHttpControllerTestCase
{
    protected $services;
    protected $settings;
    protected $connection;
    protected $accessVersion;
    protected $trustedProxies;

    public function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getApplication()->getServiceManager();
        $this->settings = $this->services->get('Omeka\Settings');
        $this->connection = $this->services->get('Omeka\Connection');
        $this->accessVersion = $this->connection->fetchOne('SELECT `version` FROM `module` WHERE `id` = "Access"');
        $this->trustedProxies = $this->settings->get('trusted_proxies');
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
        if ($this->accessVersion) {
            $this->connection->executeStatement('UPDATE `module` SET `version` = ? WHERE `id` = "Access"', [$this->accessVersion]);
        } else {
            $this->connection->executeStatement('DELETE FROM `module` WHERE `id` = "Access"');
        }
        $this->settings->set('trusted_proxies', $this->trustedProxies);
        parent::tearDown();
    }

    protected function setAccessVersion(string $version): void
    {
        if ($this->accessVersion) {
            $this->connection->executeStatement('UPDATE `module` SET `version` = ? WHERE `id` = "Access"', [$version]);
        } else {
            $this->connection->executeStatement('INSERT INTO `module` (`id`, `is_active`, `version`) VALUES ("Access", 0, ?)', [$version]);
        }
    }

    protected function upgrade(): void
    {
        $module = new \Common\Module;
        $module->upgrade('3.4.96', '3.4.97', $this->services);
    }

    public function testPrivateProxyOfAccessIsWidened(): void
    {
        $this->setAccessVersion('3.4.51');
        $this->settings->set('trusted_proxies', ['172.19.0.16']);
        $this->upgrade();
        $this->assertSame(['172.16.0.0/12'], $this->settings->get('trusted_proxies'));
    }

    public function testPublicProxyIsKept(): void
    {
        $this->setAccessVersion('3.4.51');
        $this->settings->set('trusted_proxies', ['198.51.100.7']);
        $this->upgrade();
        $this->assertSame(['198.51.100.7'], $this->settings->get('trusted_proxies'));
    }

    public function testListOfProxiesIsKept(): void
    {
        $this->setAccessVersion('3.4.51');
        $this->settings->set('trusted_proxies', ['172.19.0.16', '172.19.0.17']);
        $this->upgrade();
        $this->assertSame(['172.19.0.16', '172.19.0.17'], $this->settings->get('trusted_proxies'));
    }

    public function testProxyWithoutAccessIsKept(): void
    {
        if ($this->accessVersion) {
            $this->markTestSkipped('The module Access is installed.');
        }
        $this->settings->set('trusted_proxies', ['172.19.0.16']);
        $this->upgrade();
        $this->assertSame(['172.19.0.16'], $this->settings->get('trusted_proxies'));
    }
}
