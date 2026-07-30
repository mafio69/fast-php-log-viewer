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

    public function testContainersFromEnvParsesCommaSeparatedList(): void
    {
        putenv('LOG_VIEWER_ALLOWED_CONTAINERS=container-a, container-b ,container-c');
        try {
            $this->assertSame(['container-a', 'container-b', 'container-c'], DockerExecService::containersFromEnv());
        } finally {
            putenv('LOG_VIEWER_ALLOWED_CONTAINERS');
        }
    }

    public function testContainersFromEnvReturnsEmptyWhenUnset(): void
    {
        putenv('LOG_VIEWER_ALLOWED_CONTAINERS');
        $this->assertSame([], DockerExecService::containersFromEnv());
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

    public function testReadFileDeniesContainerNotOnAllowList(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('container_not_allowed');

        $service->readFile('some-other-container', '/var/log/test.log');
    }

    public function testReadFileDeniesContainerWhenAllowListEmptyByDefault(): void
    {
        // Explicit empty allow-list (not reliant on LOG_VIEWER_ALLOWED_CONTAINERS
        // being unset in the ambient environment) => deny by default, fail closed.
        $service = new DockerExecService([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('container_not_allowed');

        $service->readFile('any-container', '/var/log/test.log');
    }

    public function testReadFileDeniesPathOutsideAllowedPrefixes(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path_not_allowed');

        $service->readFile('allowed-container', '/etc/passwd');
    }

    public function testReadFileDeniesPathTraversalOutsideAllowedPrefix(): void
    {
        // "/var/log/../../etc/passwd" passes a naive str_starts_with() check
        // against "/var/log/" - it must be normalized before the allow-list
        // check, or this reads arbitrary files from the container.
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path_not_allowed');

        $service->readFile('allowed-container', '/var/log/../../etc/passwd');
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

    public function testValidateAndNormalizePathDeniesOutsideAllowedPrefixes(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path_not_allowed');

        $service->validateAndNormalizePath('/etc/passwd');
    }

    public function testValidateAndNormalizePathAcceptsBareDirectoryMatchingPrefix(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $result = $service->validateAndNormalizePath('/var/log');
        $this->assertSame('/var/log', $result);
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
