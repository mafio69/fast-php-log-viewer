<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogScanner;
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
        $logScanner = $this->createMock(LogScanner::class);
        $this->controller = new DirectoryController($this->logConfig, $logScanner);
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

    public function testGetDefaultDirectoriesReturnsFourEntries(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/config/default-directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getDefaultDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);

        $this->assertCount(4, $body);
        $this->assertSame('docker:/var/log', $body[0]['key']);
        $this->assertSame('/var/log', $body[0]['path']);
        $this->assertSame('host:/var/log', $body[1]['key']);
        $this->assertSame('/host/var/log', $body[1]['path']);
        $this->assertSame('host-home:~/logs', $body[2]['key']);
        $this->assertSame('/host/home/logs', $body[2]['path']);
        $this->assertSame('repository:logs', $body[3]['key']);
        $this->assertSame('logs/', $body[3]['path']);
    }

}
