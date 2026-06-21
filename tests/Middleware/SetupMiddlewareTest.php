<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Middleware;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Middleware\SetupMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class SetupMiddlewareTest extends TestCase
{
    private ConfigManager $configManager;
    private SetupMiddleware $middleware;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));

        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
        $this->middleware = new SetupMiddleware($this->configManager);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfig)) {
            @unlink($this->tempConfig);
        }
        if (file_exists($this->tempEnv)) {
            @unlink($this->tempEnv);
        }
    }

    public function testBlocksDirectoriesWhenSetupIncomplete(): void
    {
        // Setup nie jest kompletny (brak pliku konfiguracyjnego)
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/directories');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(503, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $body = (string)$response->getBody();
        $this->assertStringContainsString('setup_required', $body);
    }

    public function testAllowsSetupEndpointsWithoutSetup(): void
    {
        // Setup nie jest kompletny, ale endpoint setup powinien być dostępny
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/setup/status');

        $responseFactory = new ResponseFactory();
        $expectedResponse = $responseFactory->createResponse(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAllowsDirectoriesWhenSetupComplete(): void
    {
        // Setup jest kompletny
        $this->configManager->saveConfig(['setup_complete' => true]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/directories');

        $responseFactory = new ResponseFactory();
        $expectedResponse = $responseFactory->createResponse(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBlocksFilesWhenSetupIncomplete(): void
    {
        // Setup nie jest kompletny
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testBlocksEntriesWhenSetupIncomplete(): void
    {
        // Setup nie jest kompletny
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/entries');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testAllowsUnprotectedRoutesWhenSetupIncomplete(): void
    {
        // Setup nie jest kompletny, ale niechronione endpointy powinny być dostępne
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/app-config');

        $responseFactory = new ResponseFactory();
        $expectedResponse = $responseFactory->createResponse(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
