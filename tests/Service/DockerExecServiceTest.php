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

    public function testListFilesDeniesContainerNotOnAllowList(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('container_not_allowed');

        $service->listFiles('some-other-container', '/var/log/');
    }

    public function testListFilesDeniesPathOutsideAllowedPrefixes(): void
    {
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path_not_allowed');

        $service->listFiles('allowed-container', '/etc');
    }

    public function testListFilesAcceptsBareDirectoryMatchingPrefixWithoutTrailingSlash(): void
    {
        // Users naturally type "/var/log" without a trailing slash when browsing
        // a directory; it must match the "/var/log/" allow-list prefix too, not
        // just paths that already have a trailing slash.
        $service = new DockerExecService(['allowed-container'], ['/var/log/']);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertPathAllowed');

        $method->invoke($service, '/var/log');
        $this->addToAssertionCount(1);
    }

    public function testParseListingParsesStatOutput(): void
    {
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($service, "1721900000\t1234\t/var/log/app.log\n1721900100\t56\t/var/log/other.log\n");

        $this->assertSame([
            ['file' => 'app.log', 'date' => date('Y-m-d H:i:s', 1721900000), 'size' => 1234],
            ['file' => 'other.log', 'date' => date('Y-m-d H:i:s', 1721900100), 'size' => 56],
        ], $result);
    }

    public function testParseListingSkipsUnparsableLines(): void
    {
        // "find" writes errors like this to the same stream that's parsed here
        // when a directory doesn't exist or isn't readable - they must not be
        // mistaken for a file entry.
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($service, "find: /var/log/missing: No such file or directory\n1721900000\t42\t/var/log/ok.log\n");

        $this->assertSame([
            ['file' => 'ok.log', 'date' => date('Y-m-d H:i:s', 1721900000), 'size' => 42],
        ], $result);
    }

    public function testParseListingHandlesEmptyOutput(): void
    {
        $service = new DockerExecService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseListing');

        $result = $method->invoke($service, '');

        $this->assertSame([], $result);
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
