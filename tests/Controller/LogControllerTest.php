<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\GlobLogFinder;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\PathResolver;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LogControllerTest extends TestCase
{
    private LogController $controller;
    private $logConfig;
    private $logFinder;
    private $configManager;
    private $pathResolver;
    private $accessValidator;
    private $logParser;

    protected function setUp(): void
    {
        $this->logFinder = $this->createMock(LogFinderInterface::class);
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->pathResolver = $this->createMock(PathResolver::class);
        $this->accessValidator = $this->createMock(FileAccessValidator::class);
        $this->logParser = $this->createMock(LogParser::class);

        $this->controller = new LogController(
            $this->logConfig,
            $this->configManager,
            $this->logFinder,
            $this->pathResolver,
            $this->accessValidator,
            $this->logParser,
        );
    }

    public function testGetFilesWithAbsolutePath(): void
    {
        $this->pathResolver->method('resolvePath')
            ->with('/var/log')
            ->willReturn('/var/log');

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

        $this->pathResolver->method('resolvePath')
            ->with('logs/')
            ->willReturn($expectedPath);

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

        $this->pathResolver->method('resolvePath')
            ->with('~/logs')
            ->willReturn($expectedPath);

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

        $this->accessValidator->method('isFileAllowed')
            ->with($testFile, 'logs/')
            ->willReturn(true);

        $this->logParser->method('parseFile')
            ->with($testFile)
            ->willReturn([
                ['datetime' => '2024-01-01 10:00:00', 'level' => 'INFO', 'location' => 'test.php:1', 'message' => 'test message', 'context' => []],
            ]);

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

        $this->accessValidator->method('isFileAllowed')
            ->with($testFile, '~/logs')
            ->willReturn(true);

        $this->logParser->method('parseFile')
            ->with($testFile)
            ->willReturn([
                ['datetime' => '2024-01-01 10:00:00', 'level' => 'WARNING', 'location' => 'app.php:5', 'message' => 'warning message', 'context' => []],
            ]);

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

    public function testGetFilesWithRealGlobLogFinderFindsLogsInTempDir(): void
    {
        $tmpDir = sys_get_temp_dir().'/log-viewer-getfiles-test-'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/test.log', "[2024-01-01 10:00:00] [INFO] [test.php:1] test\n");

        $logConfig = $this->createMock(LogConfig::class);
        $configManager = $this->createMock(ConfigManager::class);
        $realFinder = new GlobLogFinder();
        $resolver = $this->createMock(PathResolver::class);
        $validator = $this->createMock(FileAccessValidator::class);
        $parser = $this->createMock(LogParser::class);

        $resolver->method('resolvePath')
            ->with($tmpDir)
            ->willReturn($tmpDir);

        $controller = new LogController($logConfig, $configManager, $realFinder, $resolver, $validator, $parser);

        $request = (new RequestFactory())->createRequest('GET', '/api/files?path='.urlencode($tmpDir));
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertStringContainsString('test.log', $body[0]['file']);

        unlink($tmpDir.'/test.log');
        rmdir($tmpDir);
    }

    public function testGetFilesWithRealGlobLogFinderAndRelativePath(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $logDir = $appRoot.'/logs';

        $logConfig = $this->createMock(LogConfig::class);
        $configManager = $this->createMock(ConfigManager::class);
        $realFinder = new GlobLogFinder();
        $resolver = $this->createMock(PathResolver::class);
        $validator = $this->createMock(FileAccessValidator::class);
        $parser = $this->createMock(LogParser::class);

        $resolver->method('resolvePath')
            ->with('logs/')
            ->willReturn($logDir);

        $controller = new LogController($logConfig, $configManager, $realFinder, $resolver, $validator, $parser);

        $request = (new RequestFactory())->createRequest('GET', '/api/files?path=logs/');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertNotEmpty($body, 'Expected log files in project logs/ directory');
    }

    public function testGetFilesWithRealGlobLogFinderAndTildePath(): void
    {
        $originalHome = $_SERVER['HOME'] ?? null;
        $tmpHome = sys_get_temp_dir().'/log-viewer-tilde-getfiles-'.uniqid();
        $logDir = $tmpHome.'/logs';
        mkdir($logDir, 0755, true);
        file_put_contents($logDir.'/user.log', "[2024-01-01 10:00:00] [INFO] [test.php:1] test\n");

        $_SERVER['HOME'] = $tmpHome;

        $logConfig = $this->createMock(LogConfig::class);
        $configManager = $this->createMock(ConfigManager::class);
        $realFinder = new GlobLogFinder();
        $resolver = $this->createMock(PathResolver::class);
        $validator = $this->createMock(FileAccessValidator::class);
        $parser = $this->createMock(LogParser::class);

        $resolver->method('resolvePath')
            ->with('~/logs')
            ->willReturn($logDir);

        $controller = new LogController($logConfig, $configManager, $realFinder, $resolver, $validator, $parser);

        $request = (new RequestFactory())->createRequest('GET', '/api/files?path=~/logs');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->getFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertStringContainsString('user.log', $body[0]['file']);

        unlink($logDir.'/user.log');
        rmdir($logDir);
        rmdir($tmpHome);
        if ($originalHome !== null) {
            $_SERVER['HOME'] = $originalHome;
        } else {
            unset($_SERVER['HOME']);
        }
    }

    public function testGetEntriesBlocksAccessToForbiddenPath(): void
    {
        $this->accessValidator->method('isFileAllowed')
            ->with('/etc/passwd', null)
            ->willReturn(false);

        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'logs', 'path' => '/var/log']
        ]);

        $request = (new RequestFactory())->createRequest('GET', '/api/entries?file=/etc/passwd');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->getEntries($request, $response);

        $this->assertEquals(403, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('access_denied', $body['error']);
    }

    public function testGetEntriesBlocksPathTraversal(): void
    {
        $this->accessValidator->method('isFileAllowed')
            ->with('/var/log/../secret/file.txt', null)
            ->willReturn(false);

        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'logs', 'path' => '/var/log']
        ]);

        $request = (new RequestFactory())->createRequest('GET', '/api/entries?file=/var/log/../secret/file.txt');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->getEntries($request, $response);

        $this->assertEquals(403, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('access_denied', $body['error']);
    }

    public function testGetFilesHandlesExceptionFromFinder(): void
    {
        $this->pathResolver->method('resolvePath')
            ->with('/var/log')
            ->willReturn('/var/log');

        $this->logFinder->method('findAll')
            ->will($this->throwException(new \Exception('Permission denied')));

        $request = (new RequestFactory())->createRequest('GET', '/api/files?path=/var/log');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->getFiles($request, $response);

        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('server_error', $body['error']);
        $this->assertEquals('Permission denied', $body['message']);
    }

    public function testGetEntriesFiltersByLevelCorrectly(): void
    {
        $logFile = sys_get_temp_dir() . '/test_level_filter.log';
        file_put_contents($logFile, "[2024-01-01 10:00:00] [INFO] [test.php:1] Info message\n");

        $this->accessValidator->method('isFileAllowed')
            ->with($logFile, $this->anything())
            ->willReturn(true);

        $this->logParser->method('parseFile')
            ->with($logFile)
            ->willReturn([
                ['datetime' => '2024-01-01 10:00:00', 'level' => 'INFO', 'location' => 'test.php:1', 'message' => 'Info message', 'context' => []],
                ['datetime' => '2024-01-01 10:01:00', 'level' => 'WARNING', 'location' => 'test.php:2', 'message' => 'Warning message', 'context' => []],
                ['datetime' => '2024-01-01 10:02:00', 'level' => 'INFO', 'location' => 'test.php:3', 'message' => 'Another info message', 'context' => []],
                ['datetime' => '2024-01-01 10:03:00', 'level' => 'ERROR', 'location' => 'test.php:4', 'message' => 'Error message', 'context' => []],
            ]);

        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'temp', 'path' => sys_get_temp_dir()]
        ]);

        $request = (new RequestFactory())->createRequest('GET', '/api/entries?file=' . $logFile . '&level=WARNING');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->getEntries($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);

        $this->assertCount(1, $body);
        $this->assertEquals('WARNING', $body[0]['level']);
        $this->assertEquals('Warning message', $body[0]['message']);

        unlink($logFile);
    }
}
