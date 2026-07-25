<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\AllowedContainerController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class AllowedContainerControllerTest extends TestCase
{
    private AllowedContainerController $controller;
    private LogConfig $logConfig;

    protected function setUp(): void
    {
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->controller = new AllowedContainerController($this->logConfig);
    }

    public function testListReturnsAllowedContainers(): void
    {
        $this->logConfig->method('getAllowedContainersDetailed')->willReturn([
            ['id' => 1, 'container_id' => 'my-container'],
            ['id' => 2, 'container_id' => 'other-container'],
        ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/config/allowed-containers');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->list($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertSame([
            ['id' => 1, 'container_id' => 'my-container'],
            ['id' => 2, 'container_id' => 'other-container'],
        ], $body);
    }

    public function testDeleteSuccess(): void
    {
        $this->logConfig->expects($this->once())->method('deleteAllowedContainer')->with(5)->willReturn(true);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('DELETE', '/api/config/allowed-containers/5');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->delete($request, $response, ['id' => '5']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testAddSuccess(): void
    {
        $this->logConfig->expects($this->once())->method('addAllowedContainer')->with('my-container');

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/allowed-containers')
            ->withParsedBody(['container_id' => 'my-container']);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->add($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testAddRejectsEmptyContainerId(): void
    {
        $this->logConfig->expects($this->never())->method('addAllowedContainer');

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/allowed-containers')
            ->withParsedBody(['container_id' => '']);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->add($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertSame('invalid_container_id', $body['error']);
    }

    public function testAddRejectsMalformedContainerId(): void
    {
        // Same shape check as DockerExecService::validateContainerId() - a value
        // that couldn't pass there shouldn't be persisted here either.
        $this->logConfig->expects($this->never())->method('addAllowedContainer');

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/config/allowed-containers')
            ->withParsedBody(['container_id' => 'invalid;id']);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->add($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
    }
}
