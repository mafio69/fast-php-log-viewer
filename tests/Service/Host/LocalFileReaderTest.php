<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service\Host;

use Mariusz\LogViewer\Service\Host\LocalFileReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalFileReaderTest extends TestCase
{
    private string $tmpDir;
    private LocalFileReader $reader;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/local-file-reader-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->reader = new LocalFileReader();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testReadReturnsFileContents(): void
    {
        $path = $this->tmpDir . '/sample.log';
        file_put_contents($path, "line1\nline2\n");

        $this->assertSame("line1\nline2\n", $this->reader->read($path));
    }

    public function testReadReturnsEmptyStringForEmptyFile(): void
    {
        // Empty log files are a normal case (no errors yet). Returning ''
        // lets LogParser::parseString produce [] naturally; the reader does
        // not need to special-case it.
        $path = $this->tmpDir . '/empty.log';
        file_put_contents($path, '');

        $this->assertSame('', $this->reader->read($path));
    }

    public function testReadThrowsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->reader->read($this->tmpDir . '/no-such-file.log');
    }

    public function testReadThrowsForDirectoryPath(): void
    {
        // is_file() is false for a directory, so the file_not_found branch
        // catches the common mistake of passing a directory instead of a file.
        $this->expectException(RuntimeException::class);
        $this->reader->read($this->tmpDir);
    }

    public function testReadThrowsForUnreadableFile(): void
    {
        $path = $this->tmpDir . '/noperm.log';
        file_put_contents($path, 'content');
        chmod($path, 0000);

        try {
            $this->expectException(RuntimeException::class);
            $this->reader->read($path);
        } finally {
            chmod($path, 0644);
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }
}
