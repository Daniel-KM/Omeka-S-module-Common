<?php declare(strict_types=1);

namespace CommonTest;

use Laminas\Config\Reader\Ini;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Mvc\Application;
use Omeka\Test\DbTestCase;

/**
 * Bootstrap helper for module tests.
 *
 * Provide simple way for modules depending on Common to init test environment.
 * Uses \Omeka\Test\DbTestCase to handle database schema management.
 *
 * The Bootstrap automatically:
 * - Loads Omeka's vendor autoloader
 * - Loads module's vendor autoloader if present (for module-specific dependencies)
 * - Registers CommonTest\ namespace (test utilities like AbstractHttpControllerTestCase)
 * - Registers module namespaces from composer.json (autoload and autoload-dev sections)
 *
 * Usage in a module test/bootstrap.php:
 * ```php
 * <?php
 * require dirname(__DIR__, 3) . '/modules/Common/test/Bootstrap.php';
 *
 * \CommonTest\Bootstrap::bootstrap(
 *     ['Common', 'YourModule'],
 *     'YourModuleTest',
 *     __DIR__ . '/YourModuleTest'
 * );
 * ```
 *
 * Optional modules can be prefixed with "?" to install only if present:
 * ```php
 * \CommonTest\Bootstrap::bootstrap(
 *     ['Common', '?Reference', 'AdvancedSearch'],
 *     'AdvancedSearchTest',
 *     __DIR__ . '/AdvancedSearchTest'
 * );
 * ```
 *
 * Requirements:
 * - Module must have composer.json with autoload/autoload-dev PSR-4 sections
 * - Test directory structure: modules/YourModule/test/YourModuleTest/
 */
class Bootstrap
{
    /**
     * @var array Test configuration.
     */
    protected static $config;

    /**
     * @var Application
     */
    protected static $application;

    /**
     * Bootstrap the test environment.
     *
     * @param array $modules List of modules to install (in dependency order).
     * @param string|null $testNamespace PSR-4 namespace for test classes.
     * @param string|null $testPath Path to test classes directory.
     * @param bool $verbose Output progress messages.
     */
    public static function bootstrap(
        array $modules = ['Common'],
        ?string $testNamespace = null,
        ?string $testPath = null,
        bool $verbose = true
    ): void {
        // Load Omeka bootstrap (defines OMEKA_PATH, loads autoloader).
        require_once dirname(__DIR__, 3) . '/bootstrap.php';

        // Register CommonTest namespace for test utilities (with prepend to ensure priority).
        // __DIR__ is the test/ directory where this Bootstrap.php file is located.
        $loader = new \Composer\Autoload\ClassLoader();
        $loader->addPsr4('CommonTest\\', __DIR__ . '/CommonTest/');

        // Register test namespace and module namespace if provided.
        if ($testNamespace && $testPath) {
            // test/MapperTest → module root
            $moduleRoot = dirname($testPath, 2);

            // Load module's vendor autoloader if present (for module-specific dependencies).
            $moduleVendorAutoload = $moduleRoot . '/vendor/autoload.php';
            if (file_exists($moduleVendorAutoload)) {
                require_once $moduleVendorAutoload;
            }

            // Try to load autoload from module's composer.json.
            $composerFile = $moduleRoot . '/composer.json';
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);

                // Register PSR-4 autoload from composer.json.
                if (!empty($composer['autoload']['psr-4'])) {
                    foreach ($composer['autoload']['psr-4'] as $ns => $path) {
                        $loader->addPsr4($ns, $moduleRoot . '/' . $path);
                    }
                }

                // Register PSR-4 autoload-dev from composer.json.
                if (!empty($composer['autoload-dev']['psr-4'])) {
                    foreach ($composer['autoload-dev']['psr-4'] as $ns => $path) {
                        $loader->addPsr4($ns, $moduleRoot . '/' . $path);
                    }
                }
            } else {
                // Fallback: derive namespace from test namespace.
                $loader->addPsr4($testNamespace . '\\', $testPath);

                $moduleNamespace = preg_replace('/Test$/', '', $testNamespace);
                if ($moduleNamespace !== $testNamespace) {
                    $modulePath = $moduleRoot . '/src/';
                    if (is_dir($modulePath)) {
                        $loader->addPsr4($moduleNamespace . '\\', $modulePath);
                    }
                }
            }
        }

        $loader->register(true);

        // Make sure error reporting is on for testing.
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        // Use Omeka DbTestCase to drop and install schema.
        if ($verbose) {
            self::log("Dropping test database schema…");
        }
        DbTestCase::dropSchema();

        if ($verbose) {
            self::log("Creating test database schema…");
        }
        DbTestCase::installSchema();

        // Build and store test config.
        self::$config = self::buildConfig();

        // Install required modules.
        if (!empty($modules)) {
            if ($verbose) {
                self::log("Installing required modules…");
            }
            self::installModules($modules, $verbose);
        }

        if ($verbose) {
            self::log("Test database ready.");
        }
    }

    /**
     * Build test configuration.
     *
     * @return array
     */
    public static function buildConfig(): array
    {
        if (self::$config) {
            return self::$config;
        }

        $reader = new Ini();
        $config = require OMEKA_PATH . '/application/config/application.config.php';
        $config = array_merge($config, [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini'),
        ]);

        self::$config = $config;
        return $config;
    }

    /**
     * Get the test configuration.
     *
     * @return array
     */
    public static function getConfig(): array
    {
        if (!self::$config) {
            self::$config = self::buildConfig();
        }
        return self::$config;
    }

    /**
     * Get a fresh application instance.
     *
     * @return Application
     */
    public static function getApplication(): Application
    {
        return Application::init(self::getConfig());
    }

    /**
     * Install modules in order.
     *
     * Module names prefixed with "?" are optional and will be skipped silently
     * if not found. Required modules (without "?") will show a warning if not
     * found.
     *
     * @param array $modules Module names in dependency order.
     * @param bool $verbose Output progress messages.
     */
    public static function installModules(array $modules, bool $verbose = true): void
    {
        foreach ($modules as $moduleName) {
            // Check if module is optional (prefixed with "?").
            $isOptional = str_starts_with($moduleName, '?');
            if ($isOptional) {
                $moduleName = substr($moduleName, 1);
            }

            // Reinitialize with Omeka Application to load active module services.
            $application = Application::init(self::getConfig());
            $serviceLocator = $application->getServiceManager();

            // Login as admin for module installation permissions.
            $auth = $serviceLocator->get('Omeka\AuthenticationService');
            $adapter = $auth->getAdapter();
            $adapter->setIdentity('admin@example.com');
            $adapter->setCredential('root');
            $auth->authenticate();

            $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
            $entityManager = $serviceLocator->get('Omeka\EntityManager');

            $module = $moduleManager->getModule($moduleName);
            if ($module && $module->getState() === ModuleManager::STATE_NOT_INSTALLED) {
                if ($verbose) {
                    $optionalLabel = $isOptional ? ' (optional)' : '';
                    self::log("  Installing module: $moduleName$optionalLabel");
                }
                $moduleManager->install($module);
                $entityManager->flush();
                $entityManager->clear();
            } elseif ($module && $module->getState() === ModuleManager::STATE_NOT_ACTIVE) {
                if ($verbose) {
                    $optionalLabel = $isOptional ? ' (optional)' : '';
                    self::log("  Activating module: $moduleName$optionalLabel");
                }
                $moduleManager->activate($module);
                $entityManager->flush();
                $entityManager->clear();
            } elseif (!$module) {
                // Only warn for required modules, skip silently for optional.
                if ($verbose && !$isOptional) {
                    self::log("  Warning: Module $moduleName not found");
                }
            }
        }
    }

    /**
     * Install a single module (useful for test setup).
     *
     * @param string $moduleName Module name.
     * @return bool True if installed/activated successfully.
     */
    public static function installModule(string $moduleName): bool
    {
        $application = Application::init(self::getConfig());
        $serviceLocator = $application->getServiceManager();

        // Login as admin.
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();

        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $entityManager = $serviceLocator->get('Omeka\EntityManager');

        $module = $moduleManager->getModule($moduleName);
        if (!$module) {
            return false;
        }

        if ($module->getState() === ModuleManager::STATE_NOT_INSTALLED) {
            $moduleManager->install($module);
            $entityManager->flush();
            $entityManager->clear();
            return true;
        }

        if ($module->getState() === ModuleManager::STATE_NOT_ACTIVE) {
            $moduleManager->activate($module);
            $entityManager->flush();
            $entityManager->clear();
            return true;
        }

        return true; // Already active.
    }

    /**
     * Log a message to stdout.
     *
     * @param string $message
     */
    protected static function log(string $message): void
    {
        file_put_contents('php://stdout', $message . "\n");
    }
}
