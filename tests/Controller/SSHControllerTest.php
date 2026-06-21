<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\SSHController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class SSHControllerTest extends TestCase
{
    private SSHController $controller;

    protected function setUp(): void
    {
        $this->controller = new SSHController();
    }

    public function testTestConnectionWithInvalidJson(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/test-connection');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->testConnection($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('invalid_json', $body['error']);
    }

    public function testListFilesWithMissingPath(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/list-files');
        $data = [];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->listFiles($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_path', $body['error']);
    }

    public function testReadFileWithMissingPath(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
        $data = [];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->readFile($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_path', $body['error']);
    }

    public function testDownloadFileWithMissingPath(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/download-file');
        $data = [];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->downloadFile($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('missing_path', $body['error']);
    }

    public function testTestConnectionWithInvalidData(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/test-connection');
        $data = ['ssh_host' => '', 'ssh_user' => '']; // Brak hosta
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->testConnection($request, $response);

        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testTestConnectionWithValidData(): void
    {
        // Test z mockowanym SSH - w rzeczywistości SSH wymaga poprawnych danych
        // Ten test sprawdza tylko czy controller obsługuje poprawny format danych
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/test-connection');
        $data = [
            'ssh_host' => 'test.example.com',
            'ssh_user' => 'testuser',
            'ssh_port' => 22,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'testpass'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->testConnection($request, $response);

        // Oczekujemy błędu 500 ponieważ nie mamy rzeczywistego połączenia SSH
        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testListFilesWithValidData(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/list-files');
        $data = [
            'ssh_host' => 'test.example.com',
            'ssh_user' => 'testuser',
            'ssh_port' => 22,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'testpass',
            'path' => '/var/log'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->listFiles($request, $response);

        // Oczekujemy błędu 500 ponieważ nie mamy rzeczywistego połączenia SSH
        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testReadFileWithValidData(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
        $data = [
            'ssh_host' => 'test.example.com',
            'ssh_user' => 'testuser',
            'ssh_port' => 22,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'testpass',
            'path' => '/var/log/test.log'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->readFile($request, $response);

        // Oczekujemy błędu 500 ponieważ nie mamy rzeczywistego połączenia SSH
        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testDownloadFileWithValidData(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/download-file');
        $data = [
            'ssh_host' => 'test.example.com',
            'ssh_user' => 'testuser',
            'ssh_port' => 22,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'testpass',
            'path' => '/var/log/test.log'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->downloadFile($request, $response);

        // Oczekujemy błędu 500 ponieważ nie mamy rzeczywistego połączenia SSH
        $this->assertEquals(500, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testListFilesWithRealFrogConnection(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/list-files');
        $data = [
            'ssh_host' => 'frog01.mikr.us',
            'ssh_user' => 'frog',
            'ssh_port' => 10137,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'GxCdTbACI7',
            'path' => '/home/frog/test'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->listFiles($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('files', $body);
        $this->assertIsArray($body['files']);
        $this->assertGreaterThanOrEqual(2, count($body['files'])); // php_errors.log i nginx_error.log
    }

    public function testReadFileWithRealFrogConnection(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
        $data = [
            'ssh_host' => 'frog01.mikr.us',
            'ssh_user' => 'frog',
            'ssh_port' => 10137,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'GxCdTbACI7',
            'path' => '/home/frog/test/php_errors.log'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->readFile($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('entries', $body);
        $this->assertIsArray($body['entries']);
        $this->assertGreaterThanOrEqual(1, count($body['entries']));
    }

    public function testReadFileWithNginxFormat(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
        $data = [
            'ssh_host' => 'frog01.mikr.us',
            'ssh_user' => 'frog',
            'ssh_port' => 10137,
            'ssh_auth_method' => 'password',
            'ssh_password' => 'GxCdTbACI7',
            'path' => '/home/frog/test/nginx_error.log'
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->readFile($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('entries', $body);
        $this->assertIsArray($body['entries']);
    }
}
