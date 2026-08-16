<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use PDO;
use PDOException;
use RuntimeException;

class AuthService
{
    private PDO $db;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initSchema();
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to connect to SQLite: ' . $e->getMessage());
        }
    }

    private function initSchema(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL
            );
        ');
    }

    /**
     * @return array<string, mixed>
     */
    public function register(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || mb_strlen($username) < 2) {
            throw new RuntimeException('Nazwa użytkownika musi mieć co najmniej 2 znaki.');
        }

        if (mb_strlen($password) < 4) {
            throw new RuntimeException('Hasło musi mieć co najmniej 4 znaki.');
        }

        $existing = $this->findByUsername($username);
        if ($existing !== null) {
            throw new RuntimeException('Użytkownik o podanej nazwie już istnieje.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $stmt->execute([':username' => $username, ':password_hash' => $hash]);

        $userId = (int)$this->db->lastInsertId();
        $_SESSION['user_id'] = $userId;

        return ['id' => $userId, 'username' => $username];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function login(string $username, string $password): ?array
    {
        $user = $this->findByUsername(trim($username));
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        $_SESSION['user_id'] = (int)$user['id'];

        return ['id' => (int)$user['id'], 'username' => $user['username']];
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, username FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            unset($_SESSION['user_id']);
            return null;
        }

        return ['id' => (int)$user['id'], 'username' => $user['username']];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        return $user !== false ? $user : null;
    }
}
