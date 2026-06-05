<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\LogFinder;
use PHPUnit\Framework\TestCase;

class LogFinderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/log-viewer-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFindsFilesInYearMonthStructure(): void
    {
        $this->createLog('2026/05/2026-05-03.log');
        $this->createLog('2026/04/2026-04-01.log');

        $files = (new LogFinder($this->tmpDir))->findAll();

        $this->assertCount(2, $files);
        $this->assertSame('2026-05-03', $files[0]['date']); // newest first
        $this->assertSame('2026-04-01', $files[1]['date']);
    }

    public function testFindsFilesInFlatStructure(): void
    {
        $this->createLog('2026-05-03.log');

        $files = (new LogFinder($this->tmpDir))->findAll();

        $this->assertCount(1, $files);
        $this->assertSame('2026-05-03', $files[0]['date']);
    }

    public function testReturnsEmptyForEmptyDir(): void
    {
        $this->assertSame([], (new LogFinder($this->tmpDir))->findAll());
    }

    public function testNormalizePathConvertsBackslashes(): void
    {
        $this->assertSame('C:/logs/2026/05', LogFinder::normalizePath('C:\\logs\\2026\\05'));
    }

    public function testNormalizePathRemovesDoubleSlashes(): void
    {
        $this->assertSame('/var/log/nginx/error.log', LogFinder::normalizePath('/var/log//nginx/error.log'));
        $this->assertSame('/var/log/apk.log', LogFinder::normalizePath('/var/log//apk.log'));
        $this->assertSame('/var/log/app.log', LogFinder::normalizePath('/var/log///app.log'));
    }

    public function testNormalizePathRemovesTrailingSlash(): void
    {
        $this->assertSame('/var/log', LogFinder::normalizePath('/var/log/'));
        $this->assertSame('/var/log', LogFinder::normalizePath('/var/log//'));
    }

    public function testFileHasSizeAndPath(): void
    {
        $this->createLog('2026/05/2026-05-03.log', '[2026-05-03 10:00:00] [INFO] [a.php:1] hello');

        $files = (new LogFinder($this->tmpDir))->findAll();

        $this->assertArrayHasKey('path', $files[0]);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertGreaterThan(0, $files[0]['size']);
    }

    private function createLog(string $relative, string $content = ''): void
    {
        $path = $this->tmpDir . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }
}
