<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service\Docker;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\Docker\DockerLogSourceCollector;
use PHPUnit\Framework\TestCase;

final class DockerLogSourceCollectorTest extends TestCase
{
    public function testReturnsEmptyWhenNoDockerDirectories(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([]);

        $collector = new DockerLogSourceCollector($logConfig);

        $this->assertSame([], $collector->collect());
    }

    public function testFiltersOnlyDockerTypeDirectories(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'local-dir', 'path' => '/var/log', 'type' => 'local'],
            ['name' => 'docker-dir', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'my-container'],
            ['name' => 'ssh-dir', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => 'host1'],
        ]);

        $collector = new DockerLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(1, $sources);
        $this->assertSame('container', $sources[0]->type);
        $this->assertSame('my-container', $sources[0]->containerId);
        $this->assertSame('/var/log', $sources[0]->path);
    }

    public function testSkipsDockerEntryWithoutContainerId(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'no-ctr', 'path' => '/var/log', 'type' => 'docker', 'container_id' => null],
            ['name' => 'empty-ctr', 'path' => '/var/log', 'type' => 'docker', 'container_id' => ''],
            ['name' => 'has-ctr', 'path' => '/tmp/log', 'type' => 'docker', 'container_id' => 'ctr-1'],
        ]);

        $collector = new DockerLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(1, $sources);
        $this->assertSame('ctr-1', $sources[0]->containerId);
    }

    public function testUsesNameAsKeyWhenPresent(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'my-app', 'path' => '/app/logs', 'type' => 'docker', 'container_id' => 'ctr-1'],
        ]);

        $collector = new DockerLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertSame('my-app', $sources[0]->key);
    }

    public function testFallsBackToGeneratedKeyWhenNameMissing(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => '', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'ctr-1'],
        ]);

        $collector = new DockerLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertSame('container:ctr-1:/var/log', $sources[0]->key);
    }

    public function testMultipleDockerDirectories(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'app-a', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'ctr-a'],
            ['name' => 'app-b', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'ctr-b'],
        ]);

        $collector = new DockerLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(2, $sources);
        $this->assertSame('ctr-a', $sources[0]->containerId);
        $this->assertSame('ctr-b', $sources[1]->containerId);
    }
}
