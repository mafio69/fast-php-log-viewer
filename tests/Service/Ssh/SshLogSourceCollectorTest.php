<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service\Ssh;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\Ssh\SshLogSourceCollector;
use PHPUnit\Framework\TestCase;

final class SshLogSourceCollectorTest extends TestCase
{
    public function testReturnsEmptyWhenNoSshDirectories(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([]);

        $collector = new SshLogSourceCollector($logConfig);

        $this->assertSame([], $collector->collect());
    }

    public function testFiltersOnlySshType(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'local', 'path' => '/var/log', 'type' => 'local'],
            ['name' => 'ssh-dir', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => '1.mikr.us'],
            ['name' => 'docker-dir', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'ctr'],
        ]);

        $collector = new SshLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(1, $sources);
        $this->assertSame('ssh', $sources[0]->type);
        $this->assertSame('1.mikr.us', $sources[0]->sshHost);
    }

    public function testSkipsSshEntryWithoutHost(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'no-host', 'path' => '/var/log', 'type' => 'ssh'],
            ['name' => 'empty-host', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => ''],
            ['name' => 'has-host', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => 's1'],
        ]);

        $collector = new SshLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertCount(1, $sources);
        $this->assertSame('s1', $sources[0]->sshHost);
    }

    public function testUsesNameAsKey(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => 'frog', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => '1.mikr.us'],
        ]);

        $collector = new SshLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertSame('frog', $sources[0]->key);
    }

    public function testFallsBackToGeneratedKey(): void
    {
        $logConfig = $this->createMock(LogConfig::class);
        $logConfig->method('getDirectories')->willReturn([
            ['name' => '', 'path' => '/var/log', 'type' => 'ssh', 'ssh_host' => 's1'],
        ]);

        $collector = new SshLogSourceCollector($logConfig);
        $sources = $collector->collect();

        $this->assertSame('ssh:s1:/var/log', $sources[0]->key);
    }
}
