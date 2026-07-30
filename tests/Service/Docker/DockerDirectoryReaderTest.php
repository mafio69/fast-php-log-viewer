<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service\Docker;

use Mariusz\LogViewer\Service\Docker\DockerDirectoryReader;
use Mariusz\LogViewer\Service\DockerExecService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DockerDirectoryReaderTest extends TestCase
{
    private function createReader(array $allowedContainers = ['allowed-container'], array $allowedPaths = ['/var/log/']): DockerDirectoryReader
    {
        return new DockerDirectoryReader(
            new DockerExecService($allowedContainers, $allowedPaths),
        );
    }

    public function testListFilesDeniesContainerNotOnAllowList(): void
    {
        $reader = $this->createReader(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('container_not_allowed');

        $reader->listFiles('some-other-container', '/var/log/');
    }

    public function testListFilesDeniesPathOutsideAllowedPrefixes(): void
    {
        $reader = $this->createReader(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path_not_allowed');

        $reader->listFiles('allowed-container', '/etc');
    }

    public function testParseListingParsesStatOutput(): void
    {
        $reader = $this->createReader();
        $reflection = new ReflectionClass($reader);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($reader, "1721900000\t1234\t/var/log/app.log\n1721900100\t56\t/var/log/other.log\n");

        $this->assertSame([
            ['file' => 'app.log', 'date' => date('Y-m-d H:i:s', 1721900000), 'size' => 1234],
            ['file' => 'other.log', 'date' => date('Y-m-d H:i:s', 1721900100), 'size' => 56],
        ], $result);
    }

    public function testParseListingSkipsUnparsableLines(): void
    {
        $reader = $this->createReader();
        $reflection = new ReflectionClass($reader);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($reader, "find: /var/log/missing: No such file or directory\n1721900000\t42\t/var/log/ok.log\n");

        $this->assertSame([
            ['file' => 'ok.log', 'date' => date('Y-m-d H:i:s', 1721900000), 'size' => 42],
        ], $result);
    }

    public function testParseListingHandlesEmptyOutput(): void
    {
        $reader = $this->createReader();
        $reflection = new ReflectionClass($reader);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($reader, '');

        $this->assertSame([], $result);
    }
}
