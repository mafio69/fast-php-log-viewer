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

    public function testGetEntriesWithRelativeDirResolvesViaFallback(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $testDir = $appRoot.'/logs';

        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $testFile = $testDir.'/test-relative-entries.log';
        $content = "[2024-01-01 10:00:00] [INFO] [test.php:1] test message\n";
        file_put_contents($testFile, $content);

        try {
            $request = (new RequestFactory())->createRequest(
                'GET',
                '/api/entries?'.http_build_query([
                    'file' => $testFile,
                    'dir' => 'logs/',
                ])
            );
            $response = (new ResponseFactory())->createResponse();

            $result = $this->controller->getEntries($request, $response);

            $this->assertEquals(200, $result->getStatusCode());
            $body = json_decode((string)$result->getBody(), true);
            $this->assertCount(1, $body);
            $this->assertEquals('INFO', $body[0]['level']);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testGetEntriesWithTildeDirResolvesViaFallback(): void
    {
        $originalHome = $_SERVER['HOME'] ?? null;
        $tmpHome = sys_get_temp_dir().'/log-viewer-home-test-'.uniqid();
        $testDir = $tmpHome.'/logs';

        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $testFile = $testDir.'/test-tilde-entries.log';
        $content = "[2024-01-01 10:00:00] [WARNING] [app.php:5] warning message\n";
        file_put_contents($testFile, $content);

        $_SERVER['HOME'] = $tmpHome;

        try {
            $request = (new RequestFactory())->createRequest(
                'GET',
                '/api/entries?'.http_build_query([
                    'file' => $testFile,
                    'dir' => '~/logs',
                ])
            );
            $response = (new ResponseFactory())->createResponse();

            $result = $this->controller->getEntries($request, $response);

            $this->assertEquals(200, $result->getStatusCode());
            $body = json_decode((string)$result->getBody(), true);
            $this->assertCount(1, $body);
            $this->assertEquals('WARNING', $body[0]['level']);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            if (is_dir($testDir)) {
                rmdir($testDir);
            }
            if (is_dir($tmpHome)) {
                rmdir($tmpHome);
            }
            if ($originalHome !== null) {
                $_SERVER['HOME'] = $originalHome;
            } else {
                unset($_SERVER['HOME']);
            }
        }
    }
}
