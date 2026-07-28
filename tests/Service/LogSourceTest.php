<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Service\LogSource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class LogSourceTest extends TestCase
{
    public function testLocalSourceHoldsAllFields(): void
    {
        $source = new LogSource(
            key: 'local:/var/log',
            path: '/var/log',
            type: 'local',
        );

        $this->assertSame('local:/var/log', $source->key);
        $this->assertSame('/var/log', $source->path);
        $this->assertSame('local', $source->type);
        $this->assertNull($source->containerId);
        $this->assertNull($source->sshHost);
    }

    public function testContainerSourceCarriesContainerId(): void
    {
        $source = new LogSource(
            key: 'container:abc123:/var/log',
            path: '/var/log',
            type: 'container',
            containerId: 'abc123',
        );

        $this->assertSame('container', $source->type);
        $this->assertSame('abc123', $source->containerId);
        $this->assertNull($source->sshHost);
    }

    public function testSshSourceCarriesHost(): void
    {
        $source = new LogSource(
            key: 'ssh:frog.host:/var/log',
            path: '/var/log',
            type: 'ssh',
            sshHost: 'frog.host',
        );

        $this->assertSame('ssh', $source->type);
        $this->assertNull($source->containerId);
        $this->assertSame('frog.host', $source->sshHost);
    }

    public function testReadonlyClassDisallowsMutation(): void
    {
        $source = new LogSource(
            key: 'local:/var/log',
            path: '/var/log',
            type: 'local',
        );

        $reflection = new ReflectionClass(LogSource::class);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('path')->isReadOnly());
    }

    public function testTwoInstancesWithSameValuesAreEqualButNotSameRef(): void
    {
        $a = new LogSource('local:/var/log', '/var/log', 'local');
        $b = new LogSource('local:/var/log', '/var/log', 'local');

        $this->assertEquals($a, $b);
        $this->assertNotSame($a, $b);
    }
}
