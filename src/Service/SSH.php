<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * SSH connection handler for remote log file access.
 */
class SSH
{
    private $connection = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Connect to SSH server
     */
    public function connect(): bool
    {
        $host = $this->config['ssh_host'] ?? '';
        $user = $this->config['ssh_user'] ?? '';
        $port = (int)($this->config['ssh_port'] ?? 22);

        if (empty($host) || empty($user)) {
            throw new \InvalidArgumentException('SSH host and user are required');
        }

        $this->connection = @ssh2_connect($host, $port);

        if (!$this->connection) {
            throw new \RuntimeException("Failed to connect to SSH server: $host:$port");
        }

        $authMethod = $this->config['ssh_auth_method'] ?? 'password';

        if ($authMethod === 'key') {
            return $this->authenticateWithKey();
        } else {
            return $this->authenticateWithPassword();
        }
    }

    /**
     * Authenticate using SSH key
     */
    private function authenticateWithKey(): bool
    {
        $keyPath = $this->config['ssh_key_path'] ?? null;

        // Try default key paths if not specified
        if (!$keyPath) {
            $defaultKeys = [
                $_SERVER['HOME'] . '/.ssh/id_rsa',
                $_SERVER['HOME'] . '/.ssh/id_ed25519',
                $_SERVER['HOME'] . '/.ssh/id_ecdsa',
                '/home/' . get_current_user() . '/.ssh/id_rsa',
            ];

            foreach ($defaultKeys as $path) {
                if (file_exists($path)) {
                    $keyPath = $path;
                    break;
                }
            }
        }

        if (!$keyPath || !file_exists($keyPath)) {
            throw new \RuntimeException('SSH key file not found: ' . ($keyPath ?? 'default paths'));
        }

        // Try without passphrase first
        $authResult = @ssh2_auth_pubkey_file(
            $this->connection,
            $this->config['ssh_user'],
            $keyPath . '.pub',
            $keyPath
        );

        if ($authResult) {
            return true;
        }

        // Try with passphrase if provided
        if (isset($this->config['ssh_key_passphrase'])) {
            $authResult = @ssh2_auth_pubkey_file(
                $this->connection,
                $this->config['ssh_user'],
                $keyPath . '.pub',
                $keyPath,
                $this->config['ssh_key_passphrase']
            );

            return $authResult;
        }

        throw new \RuntimeException('SSH key authentication failed');
    }

    /**
     * Authenticate using password
     */
    private function authenticateWithPassword(): bool
    {
        $password = $this->config['ssh_password'] ?? null;

        if (!$password) {
            throw new \InvalidArgumentException('SSH password is required for password authentication');
        }

        $authResult = @ssh2_auth_password($this->connection, $this->config['ssh_user'], $password);

        if (!$authResult) {
            throw new \RuntimeException('SSH password authentication failed');
        }

        return true;
    }

    /**
     * Execute a command on remote server
     */
    public function exec(string $command): string
    {
        if (!$this->connection) {
            throw new \RuntimeException('SSH connection not established');
        }

        $stream = @ssh2_exec($this->connection, $command);

        if (!$stream) {
            throw new \RuntimeException('Failed to execute SSH command');
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        return $output;
    }

    /**
     * List files in a remote directory
     */
    public function listFiles(string $path): array
    {
        $command = sprintf('ls -la %s 2>/dev/null', escapeshellarg($path));
        $output = $this->exec($command);

        $files = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (preg_match('/^([\-dl])([rwx\-]{9})\s+\d+\s+\w+\s+\w+\s+(\d+)\s+(\w+\s+\d+\s+[\d\:]+)\s+(.+)$/', $line, $matches)) {
                $files[] = [
                    'type' => $matches[1] === 'd' ? 'directory' : 'file',
                    'permissions' => $matches[2],
                    'size' => (int)$matches[3],
                    'date' => $matches[4],
                    'name' => $matches[5],
                ];
            }
        }

        return $files;
    }

    /**
     * Read a remote file
     */
    public function readFile(string $path): string
    {
        $command = sprintf('cat %s 2>/dev/null', escapeshellarg($path));

        return $this->exec($command);
    }

    /**
     * Check if a remote file exists
     */
    public function fileExists(string $path): bool
    {
        $command = sprintf('test -f %s && echo "exists" || echo "not exists"', escapeshellarg($path));
        $output = trim($this->exec($command));

        return $output === 'exists';
    }

    /**
     * Check if a remote directory exists
     */
    public function directoryExists(string $path): bool
    {
        $command = sprintf('test -d %s && echo "exists" || echo "not exists"', escapeshellarg($path));
        $output = trim($this->exec($command));

        return $output === 'exists';
    }

    /**
     * Get file size
     */
    public function fileSize(string $path): int
    {
        $command = sprintf('stat -f%%z %s 2>/dev/null || stat -c%%s %s 2>/dev/null || echo "0"', escapeshellarg($path), escapeshellarg($path));
        $output = trim($this->exec($command));

        return (int)$output;
    }

    /**
     * Disconnect
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            ssh2_disconnect($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Check if SSH2 extension is available
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('ssh2');
    }
}
