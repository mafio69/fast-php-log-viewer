<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LogControllerTest extends TestCase
{
    private LogController $controller;
    private ConfigManager $configManager;
    private LogConfig $logConfig;
    private string $tempConfig;
    private string $tempEnv;
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));
        $this->tempDb = sys_get_temp_dir() . '/db_' . bin2hex(random_bytes(8)) . '.db';

        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->controller = new LogController($this->logConfig, $this->configManager);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfig)) {
            @unlink($this->tempConfig);
        }
        if (file_exists($this->tempEnv)) {
            @unlink($this->tempEnv);
        }
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testGetDirectoriesReturnsEmptyArrayWhenNoDirectories(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertEmpty($body);
    }

    public function testGetDirectoriesFiltersSshWhenDisabled(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([
            [
                'name' => 'local_logs',
                'path' => '/var/log',
                'type' => 'local'
            ],
            [
                'name' => 'remote_logs',
                'path' => '/remote/logs',
                'type' => 'ssh'
            ]
        ]);

        $this->configManager->saveConfig(['ssh_enabled' => false]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/directories');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getDirectories($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('local_logs', $body[0]['key']);
    }

    public function testGetFilesReturnsErrorWhenMissingDir(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_dir', $body['error']);
    }

    public function testGetFilesReturnsErrorWhenMissingDirWithoutQueryParams(): void
    {
        // Test bez żadnych query params - powinien zwrócić missing_dir
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_dir', $body['error']);
    }

    public function testGetFilesReturnsErrorWhenDirectoryNotFound(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'other_dir', 'path' => '/other/path']
        ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/files?dir=nonexistent');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(404, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('directory_not_found', $body['error']);
    }

    public function testGetEntriesReturnsErrorWhenMissingFile(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/entries');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getEntries($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_file', $body['error']);
    }
}
