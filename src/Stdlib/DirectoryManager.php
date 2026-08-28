<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\Log\LoggerInterface;

/**
 * Create and protect working directories under "files/".
 *
 * It factorizes many code where a directory is checked and created (Module,
 * jobs, and controllers) via the TraitModule, the service or the plugin.

 * It adds a single place where the .htaccess can be created.
 * Warning: the protection is Apache only: Nginx and other servers ignore
 * .htaccess and must deny the directory in their own configuration.
 */
class DirectoryManager
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check or create the destination folder.
     *
     * Guarantees that new entries can be created inside the directory, not that
     * existing files can be overwritten: a file may belong to another user even
     * when the directory itself is writeable.
     *
     * When $protect is true, a "deny all" .htaccess is dropped in the directory
     * so it is not served directly by Apache. Filesystem access (jobs, php) is
     * unaffected: imports, exports and cache reads still work.
     *
     * @param string $dirPath Absolute path of the directory to check.
     * @param bool $protect Deny direct web access to the directory.
     * @return string|null The dirpath if valid, else null.
     */
    public function checkDestinationDir(string $dirPath, bool $protect = false): ?string
    {
        // Create the directory if needed, tolerating a concurrent creation
        // (mkdir then fails but the directory exists). The mkdir mode is
        // altered by the umask, so group-write is enforced afterwards for
        // shared/multi-process setups (fails silently when not the owner).
        if (!file_exists($dirPath)) {
            if (!@mkdir($dirPath, 0775, true) && !is_dir($dirPath)) {
                $this->logger->err(
                    'The directory "{path}" cannot be created: {error}.', // @translate
                    ['path' => $dirPath, 'error' => error_get_last()['message'] ?? 'unknown error']
                );
                return null;
            }
            @chmod($dirPath, 0775);
        }

        // Fast checks first: cheap rejection without touching the filesystem.
        if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writeable($dirPath)) {
            $this->logger->err(
                'The path "{path}" is not a readable and writeable directory.', // @translate
                ['path' => $dirPath]
            );
            return null;
        }

        // Definitive writeability test: is_writeable() does not check the
        // execute bit nor ACLs, so actually create and remove a probe file.
        $probe = $dirPath . '/.omeka-write-test-' . getmypid() . '-' . uniqid('', true);
        if (@file_put_contents($probe, '') === false) {
            $this->logger->err(
                'The directory "{path}" is not writeable: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message'] ?? 'unknown error']
            );
            return null;
        }
        @unlink($probe);

        if ($protect) {
            $this->protectDirectory($dirPath);
        }

        return $dirPath;
    }

    /**
     * Drop a "deny direct web access" .htaccess in a directory if missing.
     *
     * Apache only: Nginx and other servers ignore .htaccess and must deny the
     * directory in their own configuration.
     *
     * @param string $dirPath Absolute path of an existing directory.
     * @return bool True if the .htaccess is present after the call.
     */
    public function protectDirectory(string $dirPath): bool
    {
        if (!is_dir($dirPath)) {
            return false;
        }
        $htaccessPath = rtrim($dirPath, '/') . '/.htaccess';
        if (file_exists($htaccessPath)) {
            return true;
        }
        $result = @file_put_contents($htaccessPath, $this->denyHtaccessContent());
        if ($result === false) {
            $this->logger->warn(
                'The directory "{path}" could not be protected with a .htaccess: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message'] ?? 'unknown error']
            );
            return false;
        }
        return true;
    }

    /**
     * Protect the sensitive sub-directories of a base directory (usually
     * "files/") with a deny-all .htaccess.
     *
     * Server side or sensitive directories (backup, import, export, log, temp,
     * contribution…) are protected; public media and derivative directories
     * (original, large, tile, iiif, asset, zip…) are never touched. An existing
     * .htaccess is never overwritten.
     *
     * @param string $baseDir Absolute path scanned for sub-directories.
     * @return array<string> Names of the directories protected by this call.
     */
    public function protectSensitiveDirectories(string $baseDir): array
    {
        $protected = [];
        if (!is_dir($baseDir)) {
            return $protected;
        }
        foreach (new \DirectoryIterator($baseDir) as $dir) {
            if (!$dir->isDir() || $dir->isDot()) {
                continue;
            }
            $name = $dir->getFilename();
            if ($this->isPublicDir($name) || !$this->isSensitiveDir($name)) {
                continue;
            }
            $htaccess = $dir->getPathname() . '/.htaccess';
            if (!file_exists($htaccess) && $this->protectDirectory($dir->getPathname())) {
                $protected[] = $name;
            }
        }
        return $protected;
    }

    /**
     * Directories serving public media or derivatives, never to be protected.
     */
    public function isPublicDir(string $name): bool
    {
        $public = [
            'original', 'large', 'medium', 'square', 'thumbnail', 'asset', 'zip',
        ];
        return in_array($name, $public, true)
            // Iiif and tiles are served publicly (directly or cached).
            || (bool) preg_match('/(iiif|tile|cache)/i', $name);
    }

    /**
     * Directories holding server side or sensitive data, to protect from a
     * direct web access.
     */
    public function isSensitiveDir(string $name): bool
    {
        return (bool) preg_match(
            '/(backup|bkp|dump|sql|import|export|log|temp|tmp|trash|contribution|contactus|userdata|private|preload|result|meminfo|triplestore)/i',
            $name
        );
    }

    /**
     * The .htaccess content denying direct web access to a private directory.
     */
    public function denyHtaccessContent(): string
    {
        return <<<'HTACCESS'
            # Deny direct web access to sensitive data inside this directory.
            # Apache only: Nginx must deny it in its own server configuration.
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order deny,allow
                Deny from all
            </IfModule>

            HTACCESS;
    }
}
