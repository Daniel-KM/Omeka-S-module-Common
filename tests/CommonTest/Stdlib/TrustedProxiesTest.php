<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\TrustedProxies;
use Omeka\Test\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function widenProvider(): array
    {
        return [
            ['172.19.0.16', '172.16.0.0/12'],
            ['10.1.2.3', '10.0.0.0/8'],
            ['192.168.1.1', '192.168.0.0/16'],
            ['127.0.0.1', '127.0.0.0/8'],
            ['::1', '::1/128'],
            ['fd12::5', 'fc00::/7'],
            ['203.0.113.10', '203.0.113.10'],
            ['2001:db8::1', '2001:db8::1'],
        ];
    }

    /**
     * @dataProvider widenProvider
     */
    public function testWiden(string $ip, string $expected): void
    {
        $this->assertSame($expected, TrustedProxies::widen($ip));
    }

    public function testDetectNeedsProxyHeaders(): void
    {
        $this->assertNull(TrustedProxies::detect(['REMOTE_ADDR' => '172.19.0.16']));
        $this->assertNull(TrustedProxies::detect(['HTTP_X_FORWARDED_FOR' => '203.0.113.50']));
        $this->assertNull(TrustedProxies::detect([
            'REMOTE_ADDR' => 'invalid',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]));
    }

    public function testDetectProxy(): void
    {
        $this->assertSame('172.16.0.0/12', TrustedProxies::detect([
            'REMOTE_ADDR' => '172.19.0.16',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]));
        $this->assertSame('198.51.100.7', TrustedProxies::detect([
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_REAL_IP' => '203.0.113.50',
        ]));
    }
}
