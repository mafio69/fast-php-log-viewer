<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Service\AuthService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AuthServiceTest extends TestCase
{
    private string $dbPath;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/auth_test_' . bin2hex(random_bytes(4)) . '.db';
        $this->auth = new AuthService($this->dbPath);

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

    public function testRegisterCreatesUserAndSetsSession(): void
    {
        $user = $this->auth->register('alice', 'secret123');

        $this->assertSame('alice', $user['username']);
        $this->assertArrayHasKey('id', $user);
        $this->assertSame($user['id'], $_SESSION['user_id']);
    }

    public function testRegisterRejectsShortUsername(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('co najmniej 2 znaki');
        $this->auth->register('a', 'secret123');
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hasło musi mieć co najmniej 4');
        $this->auth->register('alice', 'ab');
    }

    public function testRegisterRejectsDuplicateUsername(): void
    {
        $this->auth->register('alice', 'secret123');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('już istnieje');
        $this->auth->register('alice', 'otherpass');
    }

    public function testLoginWithValidCredentialsSetsSession(): void
    {
        $registered = $this->auth->register('alice', 'secret123');
        $_SESSION = [];

        $user = $this->auth->login('alice', 'secret123');

        $this->assertNotNull($user);
        $this->assertSame('alice', $user['username']);
        $this->assertSame($user['id'], $_SESSION['user_id']);
    }

    public function testLoginWithWrongPasswordReturnsNull(): void
    {
        $this->auth->register('alice', 'secret123');
        $_SESSION = [];

        $this->assertNull($this->auth->login('alice', 'wrongpass'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginWithUnknownUserReturnsNull(): void
    {
        $this->assertNull($this->auth->login('nobody', 'whatever'));
    }

    public function testGetCurrentUserReturnsNullWhenNoSession(): void
    {
        $_SESSION = [];
        $this->assertNull($this->auth->getCurrentUser());
    }

    public function testGetCurrentUserReturnsUserWhenLoggedIn(): void
    {
        $registered = $this->auth->register('alice', 'secret123');

        $current = $this->auth->getCurrentUser();

        $this->assertNotNull($current);
        $this->assertSame('alice', $current['username']);
        $this->assertSame($registered['id'], $current['id']);
    }

    public function testGetCurrentUserReturnsNullForStaleSessionId(): void
    {
        $this->auth->register('alice', 'secret123');
        $_SESSION['user_id'] = 99999;

        $this->assertNull($this->auth->getCurrentUser());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLogoutClearsSession(): void
    {
        $this->auth->register('alice', 'secret123');
        $this->assertNotEmpty($_SESSION);

        $this->auth->logout();

        $this->assertFalse(isset($_SESSION['user_id']));
    }
}
