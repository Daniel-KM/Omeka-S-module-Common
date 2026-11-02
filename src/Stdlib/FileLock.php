<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Create a file on demand once, even when concurrent processes request it.
 *
 * A file with a fixed path (cache, derivative) may be requested by many
 * visitors at the same time. The process that gets the lock builds the file in
 * its own temp file, then renames it, so a reader never gets a partial file and
 * the previous file is served until the new one is ready. The others skip the
 * build.
 *
 * The lock is a non-blocking flock(), so it is released by the system when the
 * process ends, even after a crash or a kill. To avoid a lock file by built
 * file (millions of tiles), the files share a fixed number of lock files in a
 * single directory, chosen by a hash of the path: two files with the same lock
 * file are rarely built at the same time. The lock files are never removed,
 * because removing them while another process opens them would break the lock.
 * When a lock file cannot be opened, the file is built without lock.
 *
 * @see \CommonTest\Stdlib\FileLockTest for the usage.
 */
class FileLock
{
    /**
     * Number of lock files shared by all the built files.
     */
    const LOCKS = 4096;

    /**
     * @var string
     */
    protected $lockDir;

    /**
     * @var array Lock handles by file path, null when built without lock.
     */
    protected $handles = [];

    /**
     * @param string|null $lockDir Default is "files/.locks" in Omeka. When it
     *   is not writeable, a directory of the temp directory of the system is
     *   used, the same for all the processes of the user.
     */
    public function __construct(?string $lockDir = null)
    {
        $lockDir = $lockDir ?? (defined('OMEKA_PATH') ? OMEKA_PATH . '/files/.locks' : null);
        $this->lockDir = $lockDir && $this->ensureDir($lockDir)
            ? $lockDir
            : sys_get_temp_dir() . '/omeka-locks-' . md5($lockDir ?? __DIR__);
    }

    /**
     * Get the exclusive lock to build a file, without waiting.
     *
     * @return bool False when another process or handle builds the file.
     */
    public function acquire(string $filepath): bool
    {
        if (array_key_exists($filepath, $this->handles)) {
            return false;
        }
        $handle = $this->openLockFile($filepath, true);
        if ($handle && !flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        $this->handles[$filepath] = $handle;
        return true;
    }

    public function release(string $filepath): void
    {
        if (!array_key_exists($filepath, $this->handles)) {
            return;
        }
        if ($this->handles[$filepath]) {
            flock($this->handles[$filepath], LOCK_UN);
            fclose($this->handles[$filepath]);
        }
        unset($this->handles[$filepath]);
    }

    /**
     * Check if a file is being built by any process.
     */
    public function isLocked(string $filepath): bool
    {
        if (array_key_exists($filepath, $this->handles)) {
            return true;
        }
        $handle = $this->openLockFile($filepath, false);
        if (!$handle) {
            return false;
        }
        $isLocked = !flock($handle, LOCK_SH | LOCK_NB);
        fclose($handle);
        return $isLocked;
    }

    /**
     * Get the path of the lock file shared by a file.
     */
    public function lockFilepath(string $filepath): string
    {
        return sprintf('%s/%04d.lock', $this->lockDir, crc32($filepath) % self::LOCKS);
    }

    /**
     * Open the lock file of a file, created when needed.
     *
     * A flock() does not require to write, so a lock file created by another
     * user is opened read only.
     *
     * @return resource|null
     */
    protected function openLockFile(string $filepath, bool $create)
    {
        $lockFilepath = $this->lockFilepath($filepath);
        if (is_file($lockFilepath)) {
            return @fopen($lockFilepath, 'r') ?: null;
        }
        if (!$create || !$this->ensureDir($this->lockDir)) {
            return null;
        }
        $handle = @fopen($lockFilepath, 'c');
        if ($handle) {
            @chmod($lockFilepath, 0664);
        }
        return $handle ?: null;
    }

    protected function ensureDir(string $dir): bool
    {
        return (is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir))
            && is_writeable($dir);
    }

    /**
     * Get a temp file path unique to the process, next to the file.
     *
     * The extension is kept for the tools that need it (convert, gs, etc.). The
     * temp file is in the same directory, so the rename is atomic.
     */
    public function tempFilepath(string $filepath): string
    {
        $suffix = '.' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.tmp';
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        return strlen($extension)
            ? mb_substr($filepath, 0, -strlen($extension) - 1) . $suffix . '.' . $extension
            : $filepath . $suffix;
    }

    /**
     * Replace a file by its temp file atomically, or remove the temp file.
     */
    public function commit(string $tempFilepath, string $filepath, int $mode = 0664): bool
    {
        if (!is_file($tempFilepath) || !filesize($tempFilepath)) {
            @unlink($tempFilepath);
            return false;
        }
        @chmod($tempFilepath, $mode);
        if (!@rename($tempFilepath, $filepath)) {
            @unlink($tempFilepath);
            return false;
        }
        return true;
    }

    /**
     * Write a content atomically, without lock, for quick builds.
     */
    public function write(string $filepath, string $content, int $mode = 0664): bool
    {
        $tempFilepath = $this->tempFilepath($filepath);
        return @file_put_contents($tempFilepath, $content) !== false
            && $this->commit($tempFilepath, $filepath, $mode);
    }

    /**
     * Check if a path is a temp file or a lock file of this class.
     *
     * It allows to skip them when a directory is listed or cleaned.
     */
    public static function isTempOrLockFile(string $filepath): bool
    {
        return substr($filepath, -5) === '.lock'
            || (bool) preg_match('~\.\d+-[0-9a-f]{8}\.tmp(\.[^./]+)?$~', $filepath);
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $filepath) {
            $this->release($filepath);
        }
    }
}
