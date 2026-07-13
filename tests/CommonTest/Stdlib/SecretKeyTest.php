<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\SecretKey;
use Omeka\Test\TestCase;

class SecretKeyTest extends TestCase
{
    private $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/omeka-secretkey-' . uniqid();
        mkdir($this->dir);
        putenv(SecretKey::ENV);
    }

    public function tearDown(): void
    {
        @unlink($this->dir . '/' . SecretKey::FILE);
        @rmdir($this->dir);
        putenv(SecretKey::ENV);
    }

    public function testGenerateIsBase64Of32RandomBytes(): void
    {
        $key = SecretKey::generate();
        $this->assertSame(32, strlen((string) base64_decode($key, true)));
        $this->assertNotSame(SecretKey::generate(), SecretKey::generate());
    }

    public function testResolveReturnsEmptyArrayWhenNothingIsSet(): void
    {
        $this->assertSame([], SecretKey::resolve($this->dir));
    }

    public function testStoreThenResolveRoundTrip(): void
    {
        $key = SecretKey::generate();
        $this->assertTrue(SecretKey::store($key, $this->dir));
        $this->assertSame([$key], SecretKey::resolve($this->dir));
    }

    public function testResolveReturnsAllKeysFromFileInOrder(): void
    {
        file_put_contents($this->dir . '/' . SecretKey::FILE, "<?php return ['current', 'previous'];");
        $this->assertSame(['current', 'previous'], SecretKey::resolve($this->dir));
    }

    public function testResolveNormalizesALegacyStringFile(): void
    {
        file_put_contents($this->dir . '/' . SecretKey::FILE, "<?php return 'legacy';");
        $this->assertSame(['legacy'], SecretKey::resolve($this->dir));
    }

    public function testFileTakesPrecedenceOverEnvironment(): void
    {
        $fileKey = SecretKey::generate();
        SecretKey::store($fileKey, $this->dir);
        putenv(SecretKey::ENV . '=env-key');
        $this->assertSame([$fileKey], SecretKey::resolve($this->dir));
    }

    public function testEnvironmentIsUsedWhenThereIsNoFile(): void
    {
        putenv(SecretKey::ENV . '=env-key');
        $this->assertSame(['env-key'], SecretKey::resolve($this->dir));
    }

    public function testStoreReturnsFalseWhenDirectoryIsNotWritable(): void
    {
        $this->assertFalse(SecretKey::store(SecretKey::generate(), $this->dir . '/missing'));
    }
}
