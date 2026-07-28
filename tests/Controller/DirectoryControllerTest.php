<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Service\LogScanner;
use Mariusz\LogViewer\Service\LogSource;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class DirectoryControllerTest extends TestCase
{
    private DirectoryController $controller;
    private LogConfig $logConfig;
    private LogSourceCollectorInterface $collector;

    protected function setUp(): void
    {
        $this->logConfig = $this->createMock(LogConfig::class);
        $logScanner = $this->createMock(LogScanner::class);
        $this->collector = $this->createMock(LogSourceCollectorInterface::class);
        $this->controller = new DirectoryController($this->logConfig, $logScanner, $this->collector);
    }

    public function testAddDirectorySuccess(): void
    {
        $this->logConfig->method('addDirectory')->willReturn(1);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/directories');
        $data = ['name' => 'test_dir', 'path' => '/var/log', 'type' => 'local'];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->add($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(1, $body['id']);
    }

    public function testAddDirectoryWithInvalidJson(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->add($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('invalid_json', $body['error']);
    }

    public function testUpdateDirectorySuccess(): void
    {
        $this->logConfig->method('updateDirectory')->willReturn(true);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('PUT', '/api/config/directories/1');
        $data = ['name' => 'updated_dir'];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->update($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testDeleteDirectorySuccess(): void
    {
        $this->logConfig->method('deleteDirectory')->willReturn(true);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('DELETE', '/api/config/directories/1');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->delete($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testGetDefaultDirectoriesReturnsFiveEntries(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/config/default-directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getDefaultDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);

        $this->assertCount(5, $body);
        $this->assertSame('docker:/var/log', $body[0]['key']);
        $this->assertSame('/var/log', $body[0]['path']);
        $this->assertSame('docker:/var/log/nginx', $body[1]['key']);
        $this->assertSame('/var/log/nginx', $body[1]['path']);
        $this->assertSame('host:/var/log', $body[2]['key']);
        $this->assertSame('/host/var/log', $body[2]['path']);
        $this->assertSame('host-home:~/logs', $body[3]['key']);
        $this->assertSame('/host/home/logs', $body[3]['path']);
        $this->assertSame('repository:logs', $body[4]['key']);
        $this->assertSame('logs/', $body[4]['path']);
    }

    public function testGetDeferredDirectoriesReturnsLogConfigResult(): void
    {
        $this->logConfig->method('getDeferredDirectories')->willReturn([
            ['id' => 1, 'key' => 'old-dir', 'name' => 'old-dir', 'path' => '/var/log/old', 'type' => 'local', 'container_id' => null, 'valid' => true],
        ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/config/directories/deferred');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getDeferredDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertSame('old-dir', $body[0]['name']);
    }

    public function testScanDirectoriesDelegatesToCollectorThenScanner(): void
    {
        // Collector returns one host source; LogScanner returns its file list.
        // The controller must compose both and emit the existing JSON shape
        // (path/name/type/file_count/files as the front-end already expects).
        $collector = $this->createMock(LogSourceCollectorInterface::class);
        $collector->method('collect')->willReturn([
            new LogSource('local:/var/log', '/var/log', 'local'),
        ]);

        $logScanner = $this->createMock(LogScanner::class);
        $logScanner->method('scanDirectory')
            ->with('/var/log')
            ->willReturn([
                ['path' => '/var/log/syslog', 'name' => 'syslog', 'size' => 1024, 'mtime' => 0, 'extension' => 'log'],
            ]);

        $controller = new DirectoryController($this->logConfig, $logScanner, $collector);
        $request = (new RequestFactory())->createRequest('GET', '/api/directories/scan');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->scanDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);

        $this->assertArrayHasKey('/var/log', $body);
        $entry = $body['/var/log'];
        $this->assertSame('/var/log', $entry['path']);
        $this->assertSame('log', $entry['name']);
        $this->assertSame('local', $entry['type']);
        $this->assertSame(1, $entry['file_count']);
        $this->assertCount(1, $entry['files']);
        $this->assertSame('syslog', $entry['files'][0]['name']);
    }

    public function testScanDirectoriesOmitsEmptySources(): void
    {
        $collector = $this->createMock(LogSourceCollectorInterface::class);
        $collector->method('collect')->willReturn([
            new LogSource('local:/var/log', '/var/log', 'local'),
            new LogSource('local:/empty', '/empty', 'local'),
        ]);

        $logScanner = $this->createMock(LogScanner::class);
        $logScanner->method('scanDirectory')
            ->willReturnCallback(fn ($path) => $path === '/var/log'
                ? [['path' => '/var/log/syslog', 'name' => 'syslog', 'size' => 1024, 'mtime' => 0, 'extension' => 'log']]
                : []);

        $controller = new DirectoryController($this->logConfig, $logScanner, $collector);
        $request = (new RequestFactory())->createRequest('GET', '/api/directories/scan');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->scanDirectories($request, $response);
        $body = json_decode((string)$result->getBody(), true);

        $this->assertCount(1, $body);
        $this->assertArrayHasKey('/var/log', $body);
        $this->assertArrayNotHasKey('/empty', $body);
    }

    public function testScanDirectoriesReturnsEmptyForNoSources(): void
    {
        $collector = $this->createMock(LogSourceCollectorInterface::class);
        $collector->method('collect')->willReturn([]);

        $logScanner = $this->createMock(LogScanner::class);
        $controller = new DirectoryController($this->logConfig, $logScanner, $collector);
        $request = (new RequestFactory())->createRequest('GET', '/api/directories/scan');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->scanDirectories($request, $response);
        $body = json_decode((string)$result->getBody(), true);

        $this->assertSame([], $body);
    }
}
