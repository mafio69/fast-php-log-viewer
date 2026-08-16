<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\AuthController;
use Mariusz\LogViewer\Service\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class AuthControllerTest extends TestCase
{
    private string $dbPath;
    private AuthService $auth;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/auth_ctrl_' . bin2hex(random_bytes(4)) . '.db';
        $this->auth = new AuthService($this->dbPath);
        $this->controller = new AuthController($this->auth);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        @unlink($this->dbPath);
    }

    private function jsonRequest(string $method, string $uri, array $data = []): array
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest($method, $uri);
        if ($data) {
            $request = $request->withParsedBody($data);
        }
        $response = (new ResponseFactory())->createResponse();
        $result = $this->controller->{$data['_action'] ?? 'login'}($request, $response);
        return [
            'status' => $result->getStatusCode(),
            'body' => json_decode((string)$result->getBody(), true),
        ];
    }

    public function testRegisterReturnsSuccess(): void
    {
        $r = $this->jsonRequest('POST', '/api/auth/register', [
            'username' => 'alice',
            'password' => 'secret123',
            '_action' => 'register',
        ]);

        $this->assertEquals(200, $r['status']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('alice', $r['body']['user']['username']);
    }

    public function testRegisterWithMissingFieldsReturns400(): void
    {
        $r = $this->jsonRequest('POST', '/api/auth/register', [
            'username' => '',
            'password' => '',
            '_action' => 'register',
        ]);

        $this->assertEquals(400, $r['status']);
        $this->assertArrayHasKey('error', $r['body']);
    }

    public function testRegisterWithInvalidJsonReturns400(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/auth/register');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->register($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('invalid_json', $body['error']);
    }

    public function testRegisterDuplicateReturns400(): void
    {
        $this->auth->register('alice', 'secret123');

        $r = $this->jsonRequest('POST', '/api/auth/register', [
            'username' => 'alice',
            'password' => 'otherpass',
            '_action' => 'register',
        ]);

        $this->assertEquals(400, $r['status']);
        $this->assertStringContainsString('już istnieje', $r['body']['error']);
    }

    public function testLoginWithValidCredentialsReturnsSuccess(): void
    {
        $this->auth->register('alice', 'secret123');
        $_SESSION = [];

        $r = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'alice',
            'password' => 'secret123',
            '_action' => 'login',
        ]);

        $this->assertEquals(200, $r['status']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('alice', $r['body']['user']['username']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->auth->register('alice', 'secret123');

        $r = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'alice',
            'password' => 'wrongpass',
            '_action' => 'login',
        ]);

        $this->assertEquals(401, $r['status']);
        $this->assertArrayHasKey('error', $r['body']);
    }

    public function testSessionReturnsUnauthenticatedWhenNotLoggedIn(): void
    {
        $_SESSION = [];
        $request = (new RequestFactory())->createRequest('GET', '/api/auth/session');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->session($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertFalse($body['authenticated']);
    }

    public function testSessionReturnsAuthenticatedWhenLoggedIn(): void
    {
        $this->auth->register('alice', 'secret123');

        $request = (new RequestFactory())->createRequest('GET', '/api/auth/session');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->session($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['authenticated']);
        $this->assertSame('alice', $body['user']['username']);
    }

    public function testLogoutClearsSession(): void
    {
        $this->auth->register('alice', 'secret123');

        $request = (new RequestFactory())->createRequest('POST', '/api/auth/logout');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->controller->logout($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertFalse(isset($_SESSION['user_id']));
    }
}
