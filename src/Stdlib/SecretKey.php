<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Resolve and persist the application secret key used to encrypt secrets at
 * rest (@see Cipher), keeping it out of the database and out of the merged
 * application config.
 *
 * The key is resolved, in order, from:
 * 1. config/secret_key.php, a generated file returning the key (the normal
 *    case: generated on install when the config directory is writable);
 * 2. the OMEKA_SECRET_KEY environment variable (for hosts where the config
 *    directory is not writable, e.g. containers or managed hosting).
 *
 * The generated file is a PHP file returning a string, so it is not served as
 * readable text even if the config directory is exposed, and it never enters
 * the application config (config cache, debug dumps).
 *
 * The config directory defaults to OMEKA_PATH . '/config' and can be overridden
 * (mainly for testing).
 */
final class SecretKey
{
    const FILE = 'secret_key.php';

    const ENV = 'OMEKA_SECRET_KEY';

    /**
     * Resolve the secret key, or null when none is set.
     */
    public static function resolve(?string $configDir = null): ?string
    {
        $file = self::filePath($configDir);
        if (is_readable($file)) {
            $key = include $file;
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }
        $env = getenv(self::ENV);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return null;
    }

    /**
     * Generate a new secret key (base64 of 32 random bytes).
     */
    public static function generate(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Store a generated key in the config directory.
     *
     * @return bool False when the file could not be written.
     */
    public static function store(string $base64Key, ?string $configDir = null): bool
    {
        $file = self::filePath($configDir);
        $content = "<?php\nreturn " . var_export($base64Key, true) . ";\n";
        if (@file_put_contents($file, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($file, 0600);
        return true;
    }

    private static function filePath(?string $configDir): string
    {
        return ($configDir ?? OMEKA_PATH . '/config') . '/' . self::FILE;
    }
}
