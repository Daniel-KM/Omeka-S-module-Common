<?php declare(strict_types=1);

namespace CommonTest\Mvc\Controller\Plugin;

use Common\Mvc\Controller\Plugin\SpecifyMediaType;
use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Test media type detection for files that finfo misidentifies.
 *
 * Each test creates a minimal fixture with the correct magic bytes, then
 * calls the plugin with the media type that finfo would have returned.
 * The plugin must refine it to the expected type.
 */
class SpecifyMediaTypeTest extends TestCase
{
    protected SpecifyMediaType $plugin;

    protected string $fixtureDir;

    protected function setUp(): void
    {
        $logger = $this->createMock(Logger::class);
        $connection = $this->createMock(Connection::class);

        $this->plugin = new SpecifyMediaType(
            $logger,
            $connection,
            '/tmp',
            []
        );

        $this->fixtureDir = sys_get_temp_dir()
            . '/common_test_media_types_' . uniqid();
        mkdir($this->fixtureDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixtureDir)) {
            $files = glob($this->fixtureDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->fixtureDir);
        }
    }

    protected function fixture(string $name, string $content): string
    {
        $path = $this->fixtureDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    // Binary formats (application/octet-stream).

    public function testGlb(): void
    {
        // 12-byte GLB header: magic "glTF" + version 2 + length 12.
        $path = $this->fixture('model.glb',
            "glTF\x02\x00\x00\x00\x0c\x00\x00\x00");
        $this->assertSame('model/gltf-binary',
            ($this->plugin)($path, 'application/octet-stream'));
    }

    public function testFbx(): void
    {
        // 27-byte FBX header: magic + 0x1a 0x00 + version 7400.
        $path = $this->fixture('model.fbx',
            "Kaydara FBX Binary  \x00\x1a\x00\xe8\x1c\x00\x00");
        $this->assertSame('model/vnd.filmbox',
            ($this->plugin)($path, 'application/octet-stream'));
    }

    public function testUnknownBinaryPassthrough(): void
    {
        $path = $this->fixture('unknown.bin', str_repeat("\x00", 32));
        $this->assertSame('application/octet-stream',
            ($this->plugin)($path, 'application/octet-stream'));
    }

    // Text formats (text/plain).

    public function testLas(): void
    {
        // LAS 1.4: magic "LASF" + file source id + GPS time type + …
        $path = $this->fixture('cloud.las',
            "LASF\x01\x04\x00\x00" . str_repeat("\x00", 100));
        $this->assertSame('application/vnd.las',
            ($this->plugin)($path, 'text/plain'));
    }

    public function testUsdc(): void
    {
        // USD Crate: magic "PXR-USDC" + padding.
        $path = $this->fixture('scene.usdc',
            "PXR-USDC\x00\x00\x00\x00\x00\x00\x00\x00");
        $this->assertSame('model/vnd.usd',
            ($this->plugin)($path, 'text/plain'));
    }

    public function testUsda(): void
    {
        $path = $this->fixture('scene.usda',
            "#usda 1.0\n(\n    defaultPrim = \"root\"\n)\n");
        $this->assertSame('model/vnd.usda',
            ($this->plugin)($path, 'text/plain'));
    }

    public function testPlyUnix(): void
    {
        $path = $this->fixture('mesh.ply',
            "ply\nformat ascii 1.0\nelement vertex 3\n");
        $this->assertSame('application/x-ply',
            ($this->plugin)($path, 'text/plain'));
    }

    public function testPlyWindows(): void
    {
        $path = $this->fixture('mesh.ply',
            "ply\r\nformat ascii 1.0\r\nelement vertex 3\r\n");
        $this->assertSame('application/x-ply',
            ($this->plugin)($path, 'text/plain'));
    }

    public function testUnknownTextPassthrough(): void
    {
        $path = $this->fixture('readme.txt',
            "Hello, this is plain text.\n");
        $this->assertSame('text/plain',
            ($this->plugin)($path, 'text/plain'));
    }

    // JSON formats (application/json).

    public function testGltfJson(): void
    {
        $json = json_encode([
            'asset' => ['version' => '2.0', 'generator' => 'test'],
            'scene' => 0,
        ]);
        $path = $this->fixture('model.gltf', $json);
        $this->assertSame('model/gltf+json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testGeoJsonFeatureCollection(): void
    {
        $json = json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ]);
        $path = $this->fixture('map.geojson', $json);
        $this->assertSame('application/geo+json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testGeoJsonPoint(): void
    {
        $json = json_encode([
            'type' => 'Point',
            'coordinates' => [2.3522, 48.8566],
        ]);
        $path = $this->fixture('point.geojson', $json);
        $this->assertSame('application/geo+json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testJsonLd(): void
    {
        $json = json_encode([
            '@context' => 'http://schema.org',
            '@type' => 'Thing',
            'name' => 'Test',
        ]);
        $path = $this->fixture('data.jsonld', $json);
        $this->assertSame('application/ld+json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testJsonLdIiifManifest(): void
    {
        $json = json_encode([
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => 'https://example.org/manifest/1',
            'type' => 'Manifest',
            'label' => ['en' => ['Example']],
        ]);
        $path = $this->fixture('manifest.json', $json);
        $this->assertSame('application/ld+json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testUnknownJsonPassthrough(): void
    {
        $json = json_encode(['key' => 'value', 'count' => 42]);
        $path = $this->fixture('data.json', $json);
        $this->assertSame('application/json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testInvalidJsonPassthrough(): void
    {
        $path = $this->fixture('bad.json', '{not valid json}');
        $this->assertSame('application/json',
            ($this->plugin)($path, 'application/json'));
    }

    public function testLargeJsonPassthrough(): void
    {
        // Files > 5 MB are skipped (too large to decode safely).
        $path = $this->fixtureDir . '/large.json';
        $handle = fopen($path, 'w');
        fwrite($handle, '{"@context":"x","d":"');
        // Fill to just over 5 MB.
        $remaining = 5242881 - 20 - 2;
        while ($remaining > 0) {
            $chunk = min($remaining, 8192);
            fwrite($handle, str_repeat('a', $chunk));
            $remaining -= $chunk;
        }
        fwrite($handle, '"}');
        fclose($handle);
        $this->assertSame('application/json',
            ($this->plugin)($path, 'application/json'));
    }

    // JSON priority: glTF > GeoJSON > JSON-LD.

    public function testGltfTakesPriorityOverJsonLd(): void
    {
        // A glTF file with @context (unlikely but possible).
        $json = json_encode([
            '@context' => 'https://example.org',
            'asset' => ['version' => '2.0'],
        ]);
        $path = $this->fixture('priority.json', $json);
        $this->assertSame('model/gltf+json',
            ($this->plugin)($path, 'application/json'));
    }

    // HTML / hOCR.

    public function testHocrByContent(): void
    {
        $html = '<html><body>'
            . '<div class="ocr_page" id="page_1">OCR text</div>'
            . '</body></html>';
        $path = $this->fixture('page.html', $html);
        $this->assertSame('text/vnd.hocr+html',
            ($this->plugin)($path, 'text/html'));
    }

    public function testHocrByCompoundExtension(): void
    {
        $path = $this->fixture('page.hocr.html',
            '<html><body>No markers</body></html>');
        $this->assertSame('text/vnd.hocr+html',
            ($this->plugin)($path, 'text/html'));
    }

    public function testHocrByExtension(): void
    {
        $path = $this->fixture('page.hocr',
            '<html><body>No markers</body></html>');
        $this->assertSame('text/vnd.hocr+html',
            ($this->plugin)($path, 'text/html'));
    }

    public function testRegularHtmlPassthrough(): void
    {
        $path = $this->fixture('page.html',
            '<html><body><p>Hello world</p></body></html>');
        $this->assertSame('text/html',
            ($this->plugin)($path, 'text/html'));
    }

    // ZIP formats.

    public function testUsdz(): void
    {
        // Create a minimal ZIP with a .usdc first entry.
        $path = $this->fixtureDir . '/scene.usdz';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('ZipArchive not available.');
        }
        $zip->addFromString('scene.usdc',
            "PXR-USDC\x00\x00\x00\x00\x00\x00\x00\x00");
        $zip->close();
        $this->assertSame('model/vnd.usdz+zip',
            ($this->plugin)($path, 'application/zip'));
    }

    public function testUsdzWithUsda(): void
    {
        $path = $this->fixtureDir . '/scene2.usdz';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('ZipArchive not available.');
        }
        $zip->addFromString('root.usda', "#usda 1.0\n");
        $zip->close();
        $this->assertSame('model/vnd.usdz+zip',
            ($this->plugin)($path, 'application/zip'));
    }

    public function testGenericZipPassthrough(): void
    {
        $path = $this->fixtureDir . '/archive.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('ZipArchive not available.');
        }
        $zip->addFromString('readme.txt', 'Hello');
        $zip->close();
        $this->assertSame('application/zip',
            ($this->plugin)($path, 'application/zip'));
    }

    // Edge cases.

    public function testEmptyFile(): void
    {
        $path = $this->fixture('empty.bin', '');
        $this->assertSame('application/octet-stream',
            ($this->plugin)($path, 'application/octet-stream'));
    }

    public function testTinyFile(): void
    {
        $path = $this->fixture('tiny.bin', 'AB');
        $this->assertSame('application/octet-stream',
            ($this->plugin)($path, 'application/octet-stream'));
    }

    public function testEmptyTextFile(): void
    {
        $path = $this->fixture('empty.txt', '');
        $this->assertSame('text/plain',
            ($this->plugin)($path, 'text/plain'));
    }
}
