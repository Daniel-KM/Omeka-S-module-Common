# Common Test Bootstrap

This directory provides a reusable bootstrap helper for Omeka S module tests.

## Usage

In your module's `test/bootstrap.php`:

```php
<?php declare(strict_types=1);

require dirname(__DIR__, 3) . '/modules/Common/test/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'YourModule'],           // Modules to install (in dependency order)
    'YourModuleTest',                   // Test namespace (optional)
    __DIR__ . '/YourModuleTest'         // Test classes path (optional)
);
```

## Parameters

- **$modules** (array): List of module names to install, in dependency order. Each module will be installed after the previous one, allowing services to be loaded properly.

- **$testNamespace** (string|null): PSR-4 namespace for your test classes. If provided along with `$testPath`, an autoloader will be registered.

- **$testPath** (string|null): Filesystem path to your test classes directory.

- **$verbose** (bool): Whether to output progress messages. Default: `true`.

## What it does

1. Loads the Omeka bootstrap (defines `OMEKA_PATH`, loads autoloader)
2. Registers your test namespace autoloader (if provided)
3. Drops and recreates the test database schema using `Omeka\Test\DbTestCase`
4. Installs the specified modules in order

## Example: Module with multiple dependencies

```php
<?php declare(strict_types=1);

require dirname(__DIR__, 3) . '/modules/Common/test/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'ValueSuggest', 'Mapper', 'Urify'],
    'UrifyTest',
    __DIR__ . '/UrifyTest'
);
```

## Additional Methods

The `Bootstrap` class provides additional utility methods:

- `Bootstrap::getConfig()`: Get the test configuration array
- `Bootstrap::getApplication()`: Get a fresh Omeka Application instance
- `Bootstrap::installModule($name)`: Install a single module (useful in test setup)
