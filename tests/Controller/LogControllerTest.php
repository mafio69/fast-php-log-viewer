<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Service\LogFinderInterface;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LogControllerTest extends TestCase
{
    private LogController $controller;
    private LogFinderInterface $logFinder;

    protected function setUp(): void
    {
        $this->logFinder = $this->createMock(LogFinderInterface::class);
        $logConfig = $this->createMock(LogConfig::class);
        $configManager = $this->createMock(ConfigManager::class);

        $this->controller = new LogController($logConfig, $configManager, $this->logFinder);
    }

    public function testGetFilesWithAbsolutePath(): void
    {
        $this->logFinder->method('findAll')
            ->with('/var/log')
            ->willReturn([
                ['file' => 'syslog', 'date' => '2024-01-01', 'size' => 1024],
            ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files?path=/var/log');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('/var/log/syslog', $body[0]['file']);
    }

    public function testGetFilesWithRelativePath(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $expectedPath = $appRoot . '/logs/';

        $this->logFinder->method('findAll')
            ->with($expectedPath)
            ->willReturn([
                ['file' => 'app.log', 'date' => '2024-01-01', 'size' => 512],
            ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files?path=logs/');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals(rtrim($expectedPath, '/') . '/app.log', $body[0]['file']);
    }

    public function testGetFilesWithHomeRelativePath(): void
    {
        $home = $_SERVER['HOME'] ?? '/root';
        $expectedPath = $home . '/logs';

        $this->logFinder->method('findAll')
            ->with($expectedPath)
            ->willReturn([
                ['file' => 'user.log', 'date' => '2024-01-01', 'size' => 256],
            ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files?path=~/logs');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals($expectedPath . '/user.log', $body[0]['file']);
    }
}
