<?php declare(strict_types=1);

namespace CommonTest;

use Laminas\I18n\Translator\Translator;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\ServiceManager\ServiceManager;
use Omeka\Module\Exception\ModuleCannotInstallException;
use PHPUnit\Framework\TestCase;

/**
 * Tests the check of the external assets declared in composer.json.
 */
class TraitModuleExternalAssetsTest extends TestCase
{
    protected string $modulePath;

    public function setUp(): void
    {
        $this->modulePath = sys_get_temp_dir() . '/common_test_assets_' . uniqid();
        mkdir($this->modulePath);
    }

    public function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->modulePath));
    }

    public function testNoComposerFile(): void
    {
        $this->assertSame([], $this->module()->missing());
    }

    public function testNoExternalAssets(): void
    {
        $this->writeComposer(['name' => 'test/module']);
        $this->assertSame([], $this->module()->missing());
    }

    public function testMissingAssets(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/lib/' => 'https://example.org/lib.zip',
            'asset/vendor/file/file.min.js' => 'https://example.org/file.min.js',
            'asset/vendor/object/' => [
                'url' => 'https://example.org/object.tgz',
                'exclude' => ['src'],
            ],
        ]]]);
        $this->assertSame(
            ['asset/vendor/lib', 'asset/vendor/file/file.min.js', 'asset/vendor/object'],
            $this->module()->missing()
        );
    }

    public function testEmptyDirectoryIsMissing(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/lib/' => 'https://example.org/lib.zip',
        ]]]);
        mkdir($this->modulePath . '/asset/vendor/lib', 0777, true);
        $this->assertSame(['asset/vendor/lib'], $this->module()->missing());
    }

    public function testInstalledAssets(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/lib/' => 'https://example.org/lib.zip',
            'asset/vendor/file/file.min.js' => 'https://example.org/file.min.js',
        ]]]);
        mkdir($this->modulePath . '/asset/vendor/lib', 0777, true);
        mkdir($this->modulePath . '/asset/vendor/file', 0777, true);
        touch($this->modulePath . '/asset/vendor/lib/lib.js');
        touch($this->modulePath . '/asset/vendor/file/file.min.js');
        $this->assertSame([], $this->module()->missing());
    }

    public function testInstallFailsBeforePreInstall(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/lib/' => 'https://example.org/lib.zip',
        ]]]);
        $module = $this->module();
        try {
            $module->install($this->services());
            $this->fail('The install should fail.');
        } catch (ModuleCannotInstallException $e) {
            $this->assertStringContainsString('asset/vendor/lib', $e->getMessage());
        }
        $this->assertSame([], $module->steps);
    }

    public function testUpgradeFailsBeforePreUpgrade(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/file/file.min.js' => 'https://example.org/file.min.js',
        ]]]);
        $module = $this->module();
        try {
            $module->upgrade('1.0.0', '1.1.0', $this->services());
            $this->fail('The upgrade should fail.');
        } catch (ModuleCannotInstallException $e) {
            $this->assertStringContainsString('asset/vendor/file/file.min.js', $e->getMessage());
        }
        $this->assertSame([], $module->steps);
    }

    public function testUpgradeWithInstalledAssets(): void
    {
        $this->writeComposer(['extra' => ['external-assets' => [
            'asset/vendor/file/file.min.js' => 'https://example.org/file.min.js',
        ]]]);
        mkdir($this->modulePath . '/asset/vendor/file', 0777, true);
        touch($this->modulePath . '/asset/vendor/file/file.min.js');
        // The upgrade is stopped after its first step, that requires the
        // full application.
        $module = $this->module();
        $module->stopOnPreUpgrade = true;
        try {
            $module->upgrade('1.0.0', '1.1.0', $this->services());
            $this->fail('The upgrade should be stopped.');
        } catch (\LogicException $e) {
            $this->assertSame('preUpgrade', $e->getMessage());
        }
        $this->assertSame(['preUpgrade'], $module->steps);
    }

    protected function services(): ServiceManager
    {
        return new ServiceManager([
            'services' => [
                TranslatorInterface::class => new Translator(),
            ],
        ]);
    }

    protected function writeComposer(array $composer): void
    {
        file_put_contents($this->modulePath . '/composer.json', json_encode($composer));
    }

    protected function module(): object
    {
        $modulePath = $this->modulePath;
        return new class($modulePath) extends \Omeka\Module\AbstractModule {
            use \Common\TraitModule;

            public function __construct(protected string $path)
            {
            }

            protected function modulePath(): string
            {
                return $this->path;
            }

            public array $steps = [];

            public bool $stopOnPreUpgrade = false;

            public function missing(): array
            {
                return $this->missingExternalAssets();
            }

            protected function initTranslations(): self
            {
                return $this;
            }

            protected function preInstall(): void
            {
                $this->steps[] = 'preInstall';
            }

            protected function preUpgrade(?string $oldVersion, ?string $newVersion): void
            {
                $this->steps[] = 'preUpgrade';
                if ($this->stopOnPreUpgrade) {
                    throw new \LogicException('preUpgrade');
                }
            }
        };
    }
}
