<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Service\GlobLogFinder;
use PHPUnit\Framework\TestCase;

class GlobLogFinderTest extends TestCase
{
    private string $tmpDir;
    private GlobLogFinder $finder;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/log-glob-finder-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->finder = new GlobLogFinder();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFindAllReturnsEmptyForNonExistentPath(): void
    {
        $this->assertSame([], $this->finder->findAll('/nonexistent/path'));
    }

    public function testFindAllReturnsEmptyForEmptyDir(): void
    {
        $this->assertSame([], $this->finder->findAll($this->tmpDir));
    }

    public function testFindAllFindsLogFiles(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');
        file_put_contents($this->tmpDir . '/error.log', 'test');

        $files = $this->finder->findAll($this->tmpDir);

        $this->assertCount(2, $files);
        $this->assertSame('app.log', $files[0]['file']);
        $this->assertSame('error.log', $files[1]['file']);
    }

    public function testFindAllFindsPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/debug.php', 'test');

        $files = $this->finder->findAll($this->tmpDir);

        $this->assertCount(1, $files);
        $this->assertSame('debug.php', $files[0]['file']);
    }

    public function testFindAllIgnoresNonLogExtensions(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'test');
        file_put_contents($this->tmpDir . '/image.jpg', 'test');

        $this->assertSame([], $this->finder->findAll($this->tmpDir));
    }

    public function testFindAllWithTrailingSlash(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');

        $files = $this->finder->findAll($this->tmpDir . '/');

        $this->assertCount(1, $files);
        $this->assertSame('app.log', $files[0]['file']);
    }

    public function testFindAllReturnsFileSize(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'hello world');

        $files = $this->finder->findAll($this->tmpDir);

        $this->assertCount(1, $files);
        $this->assertGreaterThan(0, $files[0]['size']);
    }

    public function testFindAllReturnsFileDate(): void
    {
        file_put_contents($this->tmpDir . '/app.log', 'test');

        $files = $this->finder->findAll($this->tmpDir);

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
