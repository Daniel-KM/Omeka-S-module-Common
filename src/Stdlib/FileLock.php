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
 * The lock is a non-blocking flock() on the file "{filepath}.lock", so it is
 * released by the system when the process ends, even after a crash or a kill.
 * The lock files are kept: removing them while another process opens them would
 * break the lock.
 *
 * Usage:
 *
 *     $fileLock = new \Common\Stdlib\FileLock(); if
 *     (!$fileLock->acquire($filepath)) {
 *         return null; // In progress.
 *     }
 *     $tempFilepath = $fileLock->tempFilepath($filepath); $result =
 *     buildFile($tempFilepath); $result = $result &&
 *     $fileLock->commit($tempFilepath, $filepath);
 *     $fileLock->release($filepath);
 */
class FileLock
{
    const LOCK_EXTENSION = '.lock';

    /**
     * @var resource[] Lock handles by file path.
     */
    protected $handles = [];

    /**
     * Get the exclusive lock to build a file, without waiting.
     *
     * @return bool False when another process or handle builds the file.
     */
    public function acquire(string $filepath): bool
    {
        if (isset($this->handles[$filepath])) {
            return false;
        }
        $handle = @fopen($filepath . self::LOCK_EXTENSION, 'c');
        if (!$handle) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        $this->handles[$filepath] = $handle;
        return true;
    }

    public function release(string $filepath): void
    {
        if (!isset($this->handles[$filepath])) {
            return;
        }
        flock($this->handles[$filepath], LOCK_UN);
        fclose($this->handles[$filepath]);
        unset($this->handles[$filepath]);
    }

    /**
     * Check if a file is being built by any process.
     */
    public function isLocked(string $filepath): bool
    {
        if (isset($this->handles[$filepath])) {
            return true;
        }
        $lockFilepath = $filepath . self::LOCK_EXTENSION;
        if (!file_exists($lockFilepath)) {
            return false;
        }
        $handle = @fopen($lockFilepath, 'r');
        if (!$handle) {
            return false;
        }
        $isLocked = !flock($handle, LOCK_SH | LOCK_NB);
        fclose($handle);
        return $isLocked;
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
        return substr($filepath, -strlen(self::LOCK_EXTENSION)) === self::LOCK_EXTENSION
            || (bool) preg_match('~\.\d+-[0-9a-f]{8}\.tmp(\.[^./]+)?$~', $filepath);
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $filepath) {
            $this->release($filepath);
        }
    }
}
