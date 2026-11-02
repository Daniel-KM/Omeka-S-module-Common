<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\FileLock;
use Omeka\Test\TestCase;

class FileLockTest extends TestCase
{
    /**
     * @var string
     */
    protected $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/filelock-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/.locks/*') ?: []);
        @rmdir($this->dir . '/.locks');
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testAcquireIsExclusiveInTheProcess(): void
    {
        $filepath = $this->dir . '/1.pdf';
        $fileLock = new FileLock($this->dir . '/.locks');
        $this->assertTrue($fileLock->acquire($filepath));
        $this->assertFalse($fileLock->acquire($filepath));
        $this->assertFalse((new FileLock($this->dir . '/.locks'))->acquire($filepath));
        $this->assertTrue((new FileLock($this->dir . '/.locks'))->isLocked($filepath));
        $fileLock->release($filepath);
        $this->assertFalse($fileLock->isLocked($filepath));
        $this->assertTrue($fileLock->acquire($filepath));
    }

    public function testLockOfAnotherProcess(): void
    {
        $filepath = $this->dir . '/2.pdf';
        $code = sprintf(
            'require %s; $l = new Common\Stdlib\FileLock(%s); echo $l->acquire(%s) ? "locked\n" : "busy\n"; fgets(STDIN);',
            var_export(dirname(__DIR__, 3) . '/src/Stdlib/FileLock.php', true),
            var_export($this->dir . '/.locks', true),
            var_export($filepath, true)
        );
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w']], $pipes);
        $this->assertSame("locked\n", fgets($pipes[1]));

        $fileLock = new FileLock($this->dir . '/.locks');
        $this->assertTrue($fileLock->isLocked($filepath));
        $this->assertFalse($fileLock->acquire($filepath));

        // The system releases the lock when the process ends.
        fclose($pipes[0]);
        fclose($pipes[1]);
        proc_close($process);
        $this->assertFalse($fileLock->isLocked($filepath));
        $this->assertTrue($fileLock->acquire($filepath));
    }

    public function testLockFilesAreSharedOutsideOfTheBuiltFiles(): void
    {
        $fileLock = new FileLock($this->dir . '/.locks');
        for ($i = 0; $i < 50; ++$i) {
            $filepath = $this->dir . '/tile-' . $i . '.jpg';
            $this->assertTrue($fileLock->acquire($filepath) || $fileLock->isLocked($filepath));
            $fileLock->release($filepath);
        }
        $this->assertSame([], glob($this->dir . '/*.lock'));
        $this->assertLessThanOrEqual(FileLock::LOCKS, count(glob($this->dir . '/.locks/*.lock')));
        $this->assertStringStartsWith($this->dir . '/.locks/', $fileLock->lockFilepath($this->dir . '/1.jpg'));
    }

    public function testLockDirFallsBackToTempDir(): void
    {
        touch($this->dir . '/not-a-dir');
        $lockDir = $this->dir . '/not-a-dir/locks';
        $fileLock = new FileLock($lockDir);
        $tempLockDir = sys_get_temp_dir() . '/omeka-locks-' . md5($lockDir);
        $this->assertStringStartsWith($tempLockDir . '/', $fileLock->lockFilepath($this->dir . '/5.pdf'));
        $filepath = $this->dir . '/5.pdf';
        $this->assertTrue($fileLock->acquire($filepath));
        // Another process with the same lock dir uses the same fallback.
        $this->assertTrue((new FileLock($lockDir))->isLocked($filepath));
        $fileLock->release($filepath);
        array_map('unlink', glob($tempLockDir . '/*') ?: []);
        @rmdir($tempLockDir);
    }

    public function testCommitReplacesAtomically(): void
    {
        $filepath = $this->dir . '/3.pdf';
        file_put_contents($filepath, 'old');
        $fileLock = new FileLock($this->dir . '/.locks');
        $tempFilepath = $fileLock->tempFilepath($filepath);
        $this->assertStringEndsWith('.pdf', $tempFilepath);
        $this->assertNotSame($tempFilepath, $fileLock->tempFilepath($filepath));
        $this->assertTrue(FileLock::isTempOrLockFile($tempFilepath));
        $this->assertFalse(FileLock::isTempOrLockFile($filepath));

        file_put_contents($tempFilepath, 'new');
        $this->assertTrue($fileLock->commit($tempFilepath, $filepath));
        $this->assertSame('new', file_get_contents($filepath));
        $this->assertFileDoesNotExist($tempFilepath);

        // An empty build keeps the previous file.
        $tempFilepath = $fileLock->tempFilepath($filepath);
        touch($tempFilepath);
        $this->assertFalse($fileLock->commit($tempFilepath, $filepath));
        $this->assertSame('new', file_get_contents($filepath));
        $this->assertFileDoesNotExist($tempFilepath);

        $this->assertTrue($fileLock->write($filepath, 'written'));
        $this->assertSame('written', file_get_contents($filepath));
    }
}
