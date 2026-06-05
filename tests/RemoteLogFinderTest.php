<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\RemoteLogFinder;
use Mariusz\LogViewer\Service\SSH;
use PHPUnit\Framework\TestCase;

class RemoteLogFinderTest extends TestCase
{
    private SSH $mockSsh;

    protected function setUp(): void
    {
        $this->mockSsh = $this->createMock(SSH::class);
    }

    public function testFindAllReturnsEmptyWhenDirectoryDoesNotExist(): void
    {
        $this->mockSsh->method('directoryExists')->willReturn(false);

        $finder = new RemoteLogFinder($this->mockSsh);
        $files = $finder->findAll('/nonexistent/path');

        $this->assertSame([], $files);
    }

    public function testFindAllReturnsFilesWhenDirectoryExists(): void
    {
        $this->mockSsh->method('directoryExists')->willReturn(true);
        $this->mockSsh->method('exec')->willReturn(
            "/var/log/error.log\n/var/log/debug.log\n"
        );
        $this->mockSsh->method('fileExists')->willReturn(true);
        $this->mockSsh->method('fileSize')->willReturn(1024);

        $finder = new RemoteLogFinder($this->mockSsh);
        $files = $finder->findAll('/var/log');

        $this->assertCount(2, $files);
        $this->assertArrayHasKey('path', $files[0]);
        $this->assertArrayHasKey('name', $files[0]);
        $this->assertArrayHasKey('size', $files[0]);
    }

    public function testFindAllFiltersNonExistentFiles(): void
    {
        $this->mockSsh->method('directoryExists')->willReturn(true);
        $this->mockSsh->method('exec')->willReturn(
            "/var/log/existing.log\n/var/log/nonexistent.log\n"
        );
        $this->mockSsh->method('fileExists')->willReturnCallback(function($path) {
            return $path === '/var/log/existing.log';
        });
        $this->mockSsh->method('fileSize')->willReturn(1024);

        $finder = new RemoteLogFinder($this->mockSsh);
        $files = $finder->findAll('/var/log');

        $this->assertCount(1, $files);
        $this->assertSame('existing.log', $files[0]['name']);
    }

    public function testFindAllRemovesDuplicates(): void
    {
        $this->mockSsh->method('directoryExists')->willReturn(true);
        $this->mockSsh->method('exec')->willReturn(
            "/var/log/error.log\n/var/log/error.log\n"
        );
        $this->mockSsh->method('fileExists')->willReturn(true);
        $this->mockSsh->method('fileSize')->willReturn(1024);

        $finder = new RemoteLogFinder($this->mockSsh);
        $files = $finder->findAll('/var/log');

        $this->assertCount(1, $files);
    }

    public function testScanCommonDirectoriesScansExistingPaths(): void
    {
        $this->mockSsh->method('directoryExists')->willReturnCallback(function($path) {
            return $path === '/var/log';
        });
        $this->mockSsh->method('exec')->willReturn('/var/log/error.log');
        $this->mockSsh->method('fileExists')->willReturn(true);
        $this->mockSsh->method('fileSize')->willReturn(1024);

        $finder = new RemoteLogFinder($this->mockSsh);
        $dirs = $finder->scanCommonDirectories();

        $this->assertArrayHasKey('/var/log', $dirs);
        $this->assertArrayHasKey('path', $dirs['/var/log']);
        $this->assertArrayHasKey('name', $dirs['/var/log']);
        $this->assertArrayHasKey('file_count', $dirs['/var/log']);
        $this->assertArrayHasKey('files', $dirs['/var/log']);
    }

    public function testScanCommonDirectoriesSkipsNonExistentPaths(): void
    {
        $this->mockSsh->method('directoryExists')->willReturn(false);

        $finder = new RemoteLogFinder($this->mockSsh);
        $dirs = $finder->scanCommonDirectories();

        $this->assertEmpty($dirs);
    }
}
