<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\ClientIp;
use Omeka\Test\TestCase;

class ClientIpTest extends TestCase
{
    public function testWithoutProxyTheHeadersAreIgnored(): void
    {
        $clientIp = new ClientIp;
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
            'HTTP_X_REAL_IP' => '198.51.100.2',
        ]));
    }

    public function testLocalProxyIsTrustedByDefault(): void
    {
        $clientIp = new ClientIp;
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]));
        $this->assertSame('203.0.113.51', $clientIp->getIp([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_REAL_IP' => '203.0.113.51',
        ]));
    }

    public function testForgedLeftPartIsIgnored(): void
    {
        $clientIp = new ClientIp;
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.50',
        ]));
    }

    public function testChainOfTrustedProxies(): void
    {
        $clientIp = new ClientIp(['192.0.2.0/24', '10.0.0.1']);
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.50, 192.0.2.7',
        ]));
    }

    public function testExplicitListReplacesPrivateAddresses(): void
    {
        $clientIp = new ClientIp(['192.0.2.10']);
        $this->assertSame('10.0.0.1', $clientIp->getIp([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]));
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]));
    }

    public function testStrictModeIgnoresHeadersWithoutProxy(): void
    {
        $server = [
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ];
        $this->assertSame('172.18.0.2', (new ClientIp)->getIp($server, null, true));
        $this->assertSame('203.0.113.50', (new ClientIp(['172.18.0.0/16']))->getIp($server, null, true));
    }

    public function testSpecificTrustedProxies(): void
    {
        $clientIp = new ClientIp(['192.0.2.10']);
        $this->assertSame('203.0.113.50', $clientIp->getIp([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ], ['10.0.0.0/8']));
    }

    public function testInvalidValuesAndUnknownPeer(): void
    {
        $clientIp = new ClientIp;
        $this->assertSame('172.18.0.2', $clientIp->getIp([
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, unknown',
        ]));
        $this->assertSame(ClientIp::UNKNOWN, $clientIp->getIp([]));
    }

    public function testIpv6(): void
    {
        $clientIp = new ClientIp(['fd00::/8']);
        $this->assertSame('2001:db8::1', $clientIp->getIp([
            'REMOTE_ADDR' => 'fd00::2',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1',
        ]));
        $this->assertTrue(ClientIp::ipInCidr('2001:db8::1', '2001:db8::/32'));
        $this->assertTrue(ClientIp::ipInCidr('2001:db8::1', '2001:0db8:0:0::1'));
        $this->assertFalse(ClientIp::ipInCidr('2001:db8::1', '10.0.0.0/8'));
    }

    public function testNormalizeTrustedProxies(): void
    {
        $this->assertSame(
            ['10.0.0.1', '172.16.0.0/12', 'fd00::/8'],
            ClientIp::normalizeTrustedProxies("10.0.0.1, 172.16.0.0/12\nfoo 10.0.0.0/40 fd00::/8 10.0.0.1")
        );
    }

    public function testDetect(): void
    {
        $clientIp = new ClientIp;
        $this->assertSame('direct', $clientIp->detect(['REMOTE_ADDR' => '203.0.113.50'])['status']);
        $this->assertSame('proxy', $clientIp->detect(['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.50'])['status']);
        $detected = $clientIp->detect(['REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '203.0.113.50']);
        $this->assertSame(['peer' => '198.51.100.9', 'status' => 'untrusted'], $detected);
    }

    /**
     * A reverse proxy on the same machine connects with the address of the
     * server, that a remote client cannot use.
     */
    public function testProxyOnTheSameServerIsTrusted(): void
    {
        $server = [
            'SERVER_ADDR' => '135.148.195.196',
            'REMOTE_ADDR' => '135.148.195.196',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        ];
        $clientIp = new ClientIp;
        $this->assertSame('203.0.113.7', $clientIp->getIp($server));
        $this->assertSame(['peer' => '135.148.195.196', 'status' => 'proxy'], $clientIp->detect($server));
        // An explicit list does not remove the trust of the server itself.
        $this->assertSame('203.0.113.7', (new ClientIp(['192.0.2.10']))->getIp($server));
    }

    /**
     * On a shared hosting, another account may connect with the address of the
     * server, so the strict mode requires an explicit proxy.
     */
    public function testStrictModeDoesNotTrustTheSameServer(): void
    {
        $server = [
            'SERVER_ADDR' => '135.148.195.196',
            'REMOTE_ADDR' => '135.148.195.196',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        ];
        $this->assertSame('135.148.195.196', (new ClientIp)->getIp($server, null, true));
    }

    public function testRemotePeerIsNotTheSameServer(): void
    {
        $server = [
            'SERVER_ADDR' => '135.148.195.196',
            'REMOTE_ADDR' => '198.51.100.9',
            'HTTP_X_FORWARDED_FOR' => '135.148.195.196',
        ];
        $clientIp = new ClientIp;
        // A forged header with the address of the server is not trusted.
        $this->assertSame('198.51.100.9', $clientIp->getIp($server));
        $this->assertFalse(ClientIp::isSameServer('198.51.100.9', $server));
        $this->assertFalse(ClientIp::isSameServer('135.148.195.196', ['REMOTE_ADDR' => '135.148.195.196']));
    }

}
