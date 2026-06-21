<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Config\LogConfig;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class DirectoryControllerTest extends TestCase
{
    private DirectoryController $controller;
    private LogConfig $logConfig;

    protected function setUp(): void
    {
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->controller = new DirectoryController($this->logConfig);
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

    public function testCleanupDuplicatesSuccess(): void
    {
        $this->logConfig->method('removeDuplicates')->willReturn(2);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/cleanup-duplicates');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->cleanupDuplicates($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(2, $body['removed']);
    }

    public function testScanDirectoriesSuccess(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/scan/directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->scanDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertIsArray($body);
    }

    public function testCleanupAllowedSuccess(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([
            ['id' => 1, 'name' => 'allowed_test', 'path' => '/var/log'],
            ['id' => 2, 'name' => 'normal_dir', 'path' => '/var/log/nginx']
        ]);
        $this->logConfig->method('deleteDirectory')->willReturn(true);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/cleanup-allowed');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->cleanupAllowed($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(1, $body['removed']);
    }
}
