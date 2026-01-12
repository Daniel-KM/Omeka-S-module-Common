<?php declare(strict_types=1);

/**
 * Bootstrap for unit tests only (no database required).
 *
 * This bootstrap loads only the autoloader without initializing the database.
 * Use this for pure unit tests that don't need Omeka services.
 */

// Load the Composer autoloader
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// Register module namespaces (Omeka registers these at runtime, not via autoloader)
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('Common\\', dirname(__DIR__) . '/src');
$loader->addPsr4('CommonTest\\', __DIR__ . '/CommonTest');
$loader->register();

// Make sure error reporting is on for testing
error_reporting(E_ALL);
ini_set('display_errors', '1');
