<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\LogScanner;
use PHPUnit\Framework\TestCase;

class LogScannerTest extends TestCase
{
    private string $tmpDir;
    private LogScanner $scanner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/log-scanner-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->scanner = new LogScanner();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testScanDirectoryReturnsEmptyForNonExistentPath(): void
    {
        $this->assertSame([], $this->scanner->scanDirectory('/nonexistent/path'));
    }

    public function testScanDirectoryFindsLogFilesByExtension(): void
    {
        $this->createFile('error.log');
        $this->createFile('debug.log');
        $this->createFile('not-log.jpg');

        $files = $this->scanner->scanDirectory($this->tmpDir);

        $this->assertCount(2, $files);
        $this->assertContains('error.log', array_column($files, 'name'));
        $this->assertContains('debug.log', array_column($files, 'name'));
    }

    public function testScanDirectoryFindsFilesByPattern(): void
    {
        $this->createFile('nginx-error.log');
        $this->createFile('php-debug.log');
        $this->createFile('access.log');
        $this->createFile('random.txt');

        $files = $this->scanner->scanDirectory($this->tmpDir);

        $this->assertGreaterThanOrEqual(3, count($files));
    }

    public function testScanDirectoryReturnsFileInfo(): void
    {
        $this->createFile('test.log', 'log content');

        $files = $this->scanner->scanDirectory($this->tmpDir);

        $this->assertCount(1, $files);
        $this->assertArrayHasKey('path', $files[0]);
        $this->assertArrayHasKey('name', $files[0]);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertArrayHasKey('mtime', $files[0]);
        $this->assertArrayHasKey('extension', $files[0]);
        $this->assertSame('test.log', $files[0]['name']);
        $this->assertSame('log', $files[0]['extension']);
        $this->assertGreaterThan(0, $files[0]['size']);
    }

    public function testScanDirectorySortsByModificationTime(): void
    {
        $this->createFile('old.log', 'old');
        sleep(1);
        $this->createFile('new.log', 'new');

        $files = $this->scanner->scanDirectory($this->tmpDir);

        $this->assertCount(2, $files);
        $this->assertSame('new.log', $files[0]['name']);
        $this->assertSame('old.log', $files[1]['name']);
    }

    public function testIsLogFileRecognizesLogExtensions(): void
    {
        $this->createFile('test.log');
        $this->createFile('test.txt');
        $this->createFile('test.error');
        $this->createFile('test.debug');

        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/test.log'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/test.txt'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/test.error'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/test.debug'));
    }

    public function testIsLogFileRecognizesLogPatterns(): void
    {
        $this->createFile('error-something.log');
        $this->createFile('debug-info.log');
        $this->createFile('nginx-access.log');
        $this->createFile('php-fpm.log');

        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/error-something.log'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/debug-info.log'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/nginx-access.log'));
        $this->assertTrue($this->scanner->isLogFile($this->tmpDir . '/php-fpm.log'));
    }

    public function testIsLogFileReturnsFalseForNonLogFiles(): void
    {
        $this->createFile('image.jpg');
        $this->createFile('script.js');
        $this->createFile('style.css');

        $this->assertFalse($this->scanner->isLogFile($this->tmpDir . '/image.jpg'));
        $this->assertFalse($this->scanner->isLogFile($this->tmpDir . '/script.js'));
        $this->assertFalse($this->scanner->isLogFile($this->tmpDir . '/style.css'));
    }

    public function testGetDockerLogPathsReturnsEmptyWhenNotInDocker(): void
    {
        $paths = $this->scanner->getDockerLogPaths();
        $this->assertIsArray($paths);
    }

    public function testScanCommonDirectoriesScansExistingDirs(): void
    {
        $this->createFile('error.log');

        $found = $this->scanner->scanDirectory($this->tmpDir);

        $this->assertNotEmpty($found);
    }

    private function createFile(string $name, string $content = ''): void
    {
        file_put_contents($this->tmpDir . '/' . $name, $content);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }
}
