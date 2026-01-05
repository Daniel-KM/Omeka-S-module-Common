<?php declare(strict_types=1);

/**
 * Bootstrap for Common module tests.
 *
 * Uses the Bootstrap helper class to initialize the test environment.
 */

// Load Omeka autoloader first.
require_once dirname(__DIR__, 3) . '/bootstrap.php';

// Register autoloader for root-level classes (AbstractModule, ManageModuleAndResources, TraitModule).
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('Common\\', dirname(__DIR__));
$loader->register(true);

require_once __DIR__ . '/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(['Common'], 'CommonTest', __DIR__ . '/CommonTest');
