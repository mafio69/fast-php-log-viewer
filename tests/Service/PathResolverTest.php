<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\PathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private PathResolver $resolver;
    private $logConfig;

    protected function setUp(): void
    {
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->resolver = new PathResolver($this->logConfig);
    }

    public function testResolvePathAbsolute(): void
    {
        $this->assertSame('/var/log', $this->resolver->resolvePath('/var/log'));
    }

    public function testResolvePathRelative(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $this->assertSame($appRoot . '/logs', $this->resolver->resolvePath('logs'));
    }

    public function testResolvePathTilde(): void
    {
        $home = $_SERVER['HOME'] ?? '/root';
        $this->assertSame($home . '/logs', $this->resolver->resolvePath('~/logs'));
    }

    public function testResolveDirPathSshReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolveDirPath('ssh:server1'));
    }

    public function testResolveDirPathAbsolute(): void
    {
        $this->assertSame('/var/log', $this->resolver->resolveDirPath('/var/log'));
    }

    public function testResolveDirPathColonPrefix(): void
    {
        $this->assertSame('/var/log', $this->resolver->resolveDirPath('docker:/var/log'));
    }

    public function testResolveDirPathFromDb(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'my_logs', 'path' => '/custom/path']
        ]);

        $this->assertSame('/custom/path', $this->resolver->resolveDirPath('my_logs'));
    }

    public function testResolveDirPathFallbackToResolvePath(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $this->assertSame($appRoot . '/custom', $this->resolver->resolveDirPath('custom'));
    }
}
