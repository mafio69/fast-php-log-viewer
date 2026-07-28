<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\DefaultLogSourceCollector;
use PHPUnit\Framework\TestCase;

final class DefaultLogSourceCollectorTest extends TestCase
{
    public function testReturnsThreeDefaultSourcesWhenLogConfigEmpty(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(3, $sources);
        $this->assertSame('/var/log', $sources[0]->path);
        $this->assertSame('~/logs', $sources[1]->path);
        $this->assertSame('./logs', $sources[2]->path);
        foreach ($sources as $source) {
            $this->assertSame('local', $source->type);
            $this->assertNull($source->containerId);
            $this->assertNull($source->sshHost);
        }
    }

    public function testAppendsCustomLocalDirectoryFromLogConfig(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'my-app', 'path' => '/srv/app/logs', 'type' => 'local'],
        ]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(4, $sources);
        $custom = $sources[3];
        $this->assertSame('my-app', $custom->key);
        $this->assertSame('/srv/app/logs', $custom->path);
        $this->assertSame('local', $custom->type);
    }

    public function testAppendsSshDirectoryFromLogConfig(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'frog', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => '1.mikr.us'],
        ]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(4, $sources);
        $custom = $sources[3];
        $this->assertSame('ssh', $custom->type);
        $this->assertSame('1.mikr.us', $custom->sshHost);
        $this->assertNull($custom->containerId);
    }

    public function testDedupesByCustomKeyNameVsDefaultKey(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'local:/var/log', 'path' => '/var/log', 'type' => 'local'],
        ]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        // Default key 'local:/var/log' == custom name 'local:/var/log' → no duplicate
        $this->assertCount(3, $sources);
    }

    public function testFallsBackToTypeColonPathWhenNameMissing(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => '', 'path' => '/custom/path', 'type' => 'local'],
        ]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(4, $sources);
        $custom = $sources[3];
        $this->assertSame('local:/custom/path', $custom->key);
    }

    public function testMultipleCustomDirectories(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'app', 'path' => '/app/logs', 'type' => 'local'],
            ['name' => 'ssh-prod', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => 'prod'],
        ]);

        $collector = new DefaultLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(5, $sources);
        $this->assertSame('app', $sources[3]->key);
        $this->assertSame('ssh-prod', $sources[4]->key);
    }
}
