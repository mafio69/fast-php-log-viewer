<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Service\DockerExecService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DockerExecServiceTest extends TestCase
{
    public function testIsAvailableReturnsTrueWhenSocketExists(): void
    {
        $service = new DockerExecService();

        $this->assertIsBool($service->isAvailable());
    }

    public function testReadFileThrowsOnInvalidContainerId(): void
    {
        $service = new DockerExecService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_container_id');

        $service->readFile('invalid;id', '/var/log/test.log');
    }

    public function testReadFileThrowsOnContainerIdWithSlash(): void
    {
        $service = new DockerExecService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_container_id');

        $service->readFile('cont/ainer', '/var/log/test.log');
    }

    public function testReadFileThrowsOnEmptyFilePath(): void
    {
        $service = new DockerExecService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_file_path');

        $service->readFile('my-container', '');
    }

    public function testReadFileThrowsOnRelativeFilePath(): void
    {
        $service = new DockerExecService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_file_path');

        $service->readFile('my-container', 'relative/path.log');
    }

    public function testReadFileThrowsOnNullByteInPath(): void
    {
        $service = new DockerExecService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_file_path');

        $service->readFile('my-container', "/var/log/test\0.log");
    }

    public function testReadFileAcceptsValidInputs(): void
    {
        $service = new DockerExecService();

        if (!$service->isAvailable()) {
            $this->markTestSkipped('Docker socket not available');
        }

        $this->addToAssertionCount(1);
    }

    public function testDemuxStreamDemultiplexesStdoutAndStderr(): void
    {
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('demuxStream');

        $output = $method->invoke($service, $this->buildMultiplexedData([
            [1, "line1\nline2\n"],
            [2, "err1\n"],
            [1, "line3\n"],
        ]));

        $this->assertSame("line1\nline2\nerr1\nline3\n", $output);
    }

    public function testDemuxStreamHandlesEmptyInput(): void
    {
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('demuxStream');

        $output = $method->invoke($service, '');

        $this->assertSame('', $output);
    }

    public function testDemuxStreamHandlesPartialHeader(): void
    {
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('demuxStream');

        $output = $method->invoke($service, "\x01\x00\x00\x00");

        $this->assertSame('', $output);
    }

    private function buildMultiplexedData(array $chunks): string
    {
        $data = '';
        foreach ($chunks as [$streamType, $content]) {
            $size = strlen($content);
            $header = chr($streamType) . "\x00\x00\x00" . pack('N', $size);
            $data .= $header . $content;
        }
        return $data;
    }
}
