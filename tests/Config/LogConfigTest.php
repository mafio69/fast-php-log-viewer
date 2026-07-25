<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Config;

use Mariusz\LogViewer\Config\LogConfig;
use PHPUnit\Framework\TestCase;

class LogConfigTest extends TestCase
{
    private string $dbPath;
    private LogConfig $config;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/logconfig_test_' . bin2hex(random_bytes(4)) . '.db';
        $this->config = new LogConfig($this->dbPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        @unlink(dirname($this->dbPath) . '/logviewer_backup.json');
    }

    public function testAddDirectoryAllowsSamePathOnDifferentContainers(): void
    {
        // Two different containers can both legitimately have e.g. "/var/log" -
        // the dedup-by-path check must not collapse them into one entry.
        $idA = $this->config->addDirectory([
            'name' => 'container-a-logs', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'container-a',
        ]);
        $idB = $this->config->addDirectory([
            'name' => 'container-b-logs', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'container-b',
        ]);

        $this->assertNotSame($idA, $idB);
        $this->assertCount(2, $this->config->getDirectories());
    }

    public function testAddDirectoryIsIdempotentForSameContainerAndPath(): void
    {
        $idA = $this->config->addDirectory([
            'name' => 'container-a-logs', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'container-a',
        ]);
        $idB = $this->config->addDirectory([
            'name' => 'container-a-logs-again', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'container-a',
        ]);

        $this->assertSame($idA, $idB);
        $this->assertCount(1, $this->config->getDirectories());
    }

    public function testAddDirectoryEvictsOldestWhenOverTenActive(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->config->addDirectory(['name' => "dir-$i", 'path' => "/var/log/dir-$i", 'type' => 'local']);
        }
        $this->assertCount(10, $this->config->getDirectories());

        $this->config->addDirectory(['name' => 'dir-11', 'path' => '/var/log/dir-11', 'type' => 'local']);

        $active = $this->config->getDirectories();
        $this->assertCount(10, $active);
        $this->assertFalse(in_array('dir-1', array_column($active, 'name'), true));
        $this->assertTrue(in_array('dir-11', array_column($active, 'name'), true));
    }

    public function testGetValidDirectoriesMarksDockerEntryValidOnlyWithContainerId(): void
    {
        $this->config->addDirectory([
            'name' => 'with-container', 'path' => '/var/log', 'type' => 'docker', 'container_id' => 'my-container',
        ]);
        $this->config->addDirectory([
            'name' => 'without-container', 'path' => '/var/log/nginx', 'type' => 'docker',
        ]);

        $dirs = $this->config->getValidDirectories();
        $byName = [];
        foreach ($dirs as $d) {
            $byName[$d['name']] = $d;
        }

        $this->assertTrue($byName['with-container']['valid']);
        $this->assertSame('my-container', $byName['with-container']['container_id']);
        $this->assertFalse($byName['without-container']['valid']);
    }
}
