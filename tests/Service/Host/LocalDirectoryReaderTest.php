<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service\Host;

use Mariusz\LogViewer\Service\Host\LocalDirectoryReader;
use PHPUnit\Framework\TestCase;

final class LocalDirectoryReaderTest extends TestCase
{
    private string $tmpDir;
    private LocalDirectoryReader $reader;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/local-directory-reader-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->reader = new LocalDirectoryReader();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFindAllReturnsEmptyForNonExistentPath(): void
    {
        $this->assertSame([], $this->reader->findAll('/nonexistent/path'));
    }

    public function testFindAllReturnsEmptyForEmptyDir(): void
    {
        $this->assertSame([], $this->reader->findAll($this->tmpDir));
    }

    public function testFindAllFindsLogFiles(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');
        file_put_contents($this->tmpDir . '/error.log', 'test');

        $files = $this->reader->findAll($this->tmpDir);

        $this->assertCount(2, $files);
        $names = array_column($files, 'file');
        $this->assertContains('app.log', $names);
        $this->assertContains('error.log', $names);
    }

    public function testFindAllDoesNotGlobPhpFiles(): void
    {
        // Old GlobLogFinder globbed *.php; that risked surfacing source/config
        // files through the log viewer. LocalDirectoryReader matches only
        // *.log. This test pins that behavior so the old habit cannot return.
        file_put_contents($this->tmpDir . '/debug.php', '<?php // secret');

        $files = $this->reader->findAll($this->tmpDir);

        $this->assertSame([], $files);
    }

    public function testFindAllIgnoresNonLogExtensions(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'test');
        file_put_contents($this->tmpDir . '/image.jpg', 'test');

        $this->assertSame([], $this->reader->findAll($this->tmpDir));
    }

    public function testFindAllWithTrailingSlash(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');

        $files = $this->reader->findAll($this->tmpDir . '/');

        $this->assertCount(1, $files);
        $this->assertSame('app.log', $files[0]['file']);
    }

    public function testFindAllReturnsFileSize(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'hello world');

        $files = $this->reader->findAll($this->tmpDir);

        $this->assertCount(1, $files);
        $this->assertGreaterThan(0, $files[0]['size']);
    }

    public function testFindAllReturnsFileDate(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');

        $files = $this->reader->findAll($this->tmpDir);

        $this->assertCount(1, $files);
        $this->assertNotEmpty($files[0]['date']);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }
}
