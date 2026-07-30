<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\SSHController;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\SecurityService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class SSHControllerTest extends TestCase
{
    private SSHController $controller;

    protected function setUp(): void
    {
        // Uwaga: LogParser jest tu prawdziwą instancją (nie mockiem) celowo -
        // testy read-file/download-file z realnym frogiem sprawdzają pełny
        // pipeline SSH -> parsowanie, więc muszą używać prawdziwej logiki
        // parseString(). Wcześniej mock zawsze zwracał [] (nieskonfigurowany
        // domyślny zwrot typu array), przez co testy poza jednym wyjątkiem
        // (assertIsArray zamiast assertCount) nigdy tego nie wykrywały.
        $logParser = new LogParser();
        $securityService = $this->createMock(SecurityService::class);
        $this->controller = new SSHController($logParser, $securityService);
    }

    /**
     * Live SSH integration tests against the real "frog" server are opt-in only:
     * they never run by default (so the normal test suite / CI never dials out
     * to a real external server), and credentials come from the environment,
     * never from this file.
     *
     * @return array{ssh_host: string, ssh_user: string, ssh_port: int, ssh_auth_method: string, ssh_password: string}
     */
    private function frogConnectionData(): array
    {
        if (getenv('RUN_FROG_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_FROG_INTEGRATION_TESTS=1 plus HOST_FROG/USER_FROG/PORT_FROG/PASS_FROG to run live SSH integration tests against frog.');
        }

        $host = getenv('HOST_FROG');
        $user = getenv('USER_FROG');
        $port = getenv('PORT_FROG');
        $password = getenv('PASS_FROG');

        if ($host === false || $user === false || $port === false || $password === false) {
            $this->markTestSkipped('RUN_FROG_INTEGRATION_TESTS=1 is set but HOST_FROG/USER_FROG/PORT_FROG/PASS_FROG are not all present in the environment.');
        }

        return [
            'ssh_host' => $host,
            'ssh_user' => $user,
            'ssh_port' => (int) $port,
            'ssh_auth_method' => 'password',
            'ssh_password' => $password,
        ];
    }

    public function testExtractSSHDataReturnsExpectedKeys(): void
    {
        $reflection = new \ReflectionMethod(SSHController::class, 'extractSSHData');

        $input = [
            'ssh_host' => '192.168.1.1',
            'ssh_user' => 'admin',
            'ssh_port' => 2222,
            'ssh_auth_method' => 'key',
            'ssh_password' => 'secret',
            'ssh_key_path' => '/home/user/.ssh/id_rsa',
            'ssh_key_passphrase' => 'pass',
            'path' => '/var/log',
        ];

        $result = $reflection->invoke($this->controller, $input);

        $this->assertSame('192.168.1.1', $result['ssh_host']);
        $this->assertSame('admin', $result['ssh_user']);
        $this->assertSame(2222, $result['ssh_port']);
        $this->assertSame('key', $result['ssh_auth_method']);
        $this->assertSame('secret', $result['ssh_password']);
        $this->assertSame('/home/user/.ssh/id_rsa', $result['ssh_key_path']);
        $this->assertSame('pass', $result['ssh_key_passphrase']);
        $this->assertArrayNotHasKey('path', $result);
    }

    public function testExtractSSHDataWithEmptyInput(): void
    {
        $reflection = new \ReflectionMethod(SSHController::class, 'extractSSHData');

        $result = $reflection->invoke($this->controller, []);

        $this->assertSame('', $result['ssh_host']);
        $this->assertSame('', $result['ssh_user']);
        $this->assertSame(22, $result['ssh_port']);
        $this->assertSame('password', $result['ssh_auth_method']);
        $this->assertNull($result['ssh_password']);
        $this->assertNull($result['ssh_key_path']);
        $this->assertNull($result['ssh_key_passphrase']);
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
        $this->assertEquals('Nie podano ścieżki.', $body['error']);
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
        $this->assertEquals('Nie podano ścieżki.', $body['error']);
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
        $this->assertEquals('Nie podano ścieżki.', $body['error']);
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
            'ssh_password' => 'testpass',
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
            'path' => '/var/log',
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
            'path' => '/var/log/test.log',
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
            'path' => '/var/log/test.log',
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

    public function testTestConnectionWithRealFrogConnection(): void
    {
        $data = $this->frogConnectionData();
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/test-connection');
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->testConnection($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
    }

    public function testListFilesWithRealFrogConnection(): void
    {
        $data = $this->frogConnectionData() + ['path' => '/home/frog/test'];
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/list-files');
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
        $data = $this->frogConnectionData() + ['path' => '/home/frog/test/php_errors.log'];
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
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

    public function testDownloadFileWithRealFrogConnection(): void
    {
        $data = $this->frogConnectionData() + ['path' => '/home/frog/test/php_errors.log'];
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/download-file');
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->downloadFile($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('localPath', $body);
        $this->assertArrayHasKey('size', $body);
        $this->assertGreaterThan(0, $body['size']);

        if (isset($body['localPath']) && file_exists($body['localPath'])) {
            unlink($body['localPath']);
        }
    }

    public function testReadFileWithNginxFormat(): void
    {
        $data = $this->frogConnectionData() + ['path' => '/home/frog/test/nginx_error.log'];
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/ssh/read-file');
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
