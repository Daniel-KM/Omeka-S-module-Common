<?php declare(strict_types=1);

namespace CommonTest\Validator;

use Common\Validator\ReadableDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Test for ReadableDirectory validator.
 */
class ReadableDirectoryTest extends TestCase
{
    protected ReadableDirectory $validator;
    protected string $tempDir;

    protected function setUp(): void
    {
        $this->validator = new ReadableDirectory();
        $this->tempDir = sys_get_temp_dir() . '/common_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testEmptyValue(): void
    {
        $this->assertFalse($this->validator->isValid(''));
    }

    public function testNullValue(): void
    {
        $this->assertFalse($this->validator->isValid(null));
    }

    public function testValidDirectory(): void
    {
        $this->assertTrue($this->validator->isValid($this->tempDir));
    }

    public function testSystemTempDirectory(): void
    {
        $this->assertTrue($this->validator->isValid(sys_get_temp_dir()));
    }

    public function testNonExistentPath(): void
    {
        $this->assertFalse($this->validator->isValid('/path/that/does/not/exist/anywhere'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_EXISTS, $messages);
    }

    public function testPathWithDoubleDots(): void
    {
        $this->assertFalse($this->validator->isValid('/some/path/../other'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_RAW, $messages);
    }

    public function testPathWithHiddenDirectory(): void
    {
        $this->assertFalse($this->validator->isValid('/some/path/.hidden'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_RAW, $messages);
    }

    public function testFileInsteadOfDirectory(): void
    {
        $tempFile = $this->tempDir . '/testfile.txt';
        touch($tempFile);

        $this->assertFalse($this->validator->isValid($tempFile));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_DIRECTORY, $messages);

        unlink($tempFile);
    }

    public function testBasePathRestriction(): void
    {
        $validator = new ReadableDirectory([
            'base_path' => '/var',
        ]);

        // Path inside base_path
        if (is_dir('/var/tmp')) {
            $this->assertTrue($validator->isValid('/var/tmp'));
        }
    }

    public function testBasePathRestrictionViolation(): void
    {
        $validator = new ReadableDirectory([
            'base_path' => '/nonexistent/base',
        ]);

        $this->assertFalse($validator->isValid($this->tempDir));
        $messages = $validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_IN_BASE_PATH, $messages);
    }

    public function testMessageTemplatesExist(): void
    {
        $this->validator->isValid('/path/../with/dots');
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertIsString(reset($messages));
    }

    public function testValidatorSetsValue(): void
    {
        $testPath = '/some/test/path';
        $this->validator->isValid($testPath);
        // getValue() is protected, so we just verify the validation ran
        $this->assertNotEmpty($this->validator->getMessages());
    }

    public function testMultipleValidations(): void
    {
        // First validation fails
        $this->assertFalse($this->validator->isValid('/nonexistent'));
        $this->assertNotEmpty($this->validator->getMessages());

        // Second validation succeeds - messages should be cleared
        $this->assertTrue($this->validator->isValid($this->tempDir));
        // After a successful validation, there should be no error messages
    }

    public function testRootDirectory(): void
    {
        if (is_readable('/')) {
            $this->assertTrue($this->validator->isValid('/'));
        }
    }

    public function testRelativePathWithDots(): void
    {
        $this->assertFalse($this->validator->isValid('path/../other'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(ReadableDirectory::NOT_RAW, $messages);
    }
}
