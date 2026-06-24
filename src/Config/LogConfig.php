<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Config;

use Exception;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Manages log file configuration using SQLite for persistence.
 */
class LogConfig
{
    private PDO $db;
    private string $dbPath;

    public function __construct(?string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? dirname(__DIR__, 2) . '/data/logviewer.db';
        $this->ensureDbDirectory();
        $this->connect();
        $this->initSchema();
        $this->restoreFromBackupIfEmpty();
    }

    private function getLastErrorMessage(): string
    {
        $error = error_get_last();
        if ($error === null) {
            return '';
        }
        return sprintf(' [PHP Error: %s in %s:%d]', $error['message'], $error['file'], $error['line']);
    }

    private function ensureDbDirectory(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function connect(): void
    {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("(new PDO('sqlite:' . \$this->dbPath)) Failed to connect to SQLite: " . $e->getMessage() . " (dbPath: {$this->dbPath})" . $this->getLastErrorMessage());
        }
    }

    private function initSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS log_directories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                path TEXT NOT NULL,
                type TEXT DEFAULT 'local',
                ssh_host TEXT,
                ssh_user TEXT,
                ssh_auth_method TEXT,
                ssh_key_path TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS log_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                directory_id INTEGER,
                file_path TEXT NOT NULL,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_bookmarked INTEGER DEFAULT 0,
                FOREIGN KEY (directory_id) REFERENCES log_directories(id) ON DELETE CASCADE,
                UNIQUE(directory_id, file_path)
            );

            CREATE INDEX IF NOT EXISTS idx_log_files_path ON log_files(file_path);
            CREATE INDEX IF NOT EXISTS idx_log_directories_active ON log_directories(is_active);
        ");
    }

    /**
     * Add a log directory configuration
     * @throws Exception if directory already exists
     */
    public function addDirectory(array $config): int
    {
        // Check if directory already exists
        $stmt = $this->db->prepare("SELECT id FROM log_directories WHERE path = :path");
        $stmt->execute([':path' => $config['path']]);
        if ($stmt->fetch()) {
            throw new Exception("(\$stmt->fetch()) Directory already exists: " . $config['path'] . " (path: {$config['path']})");
        }

        $stmt = $this->db->prepare("
            INSERT INTO log_directories (name, path, type, ssh_host, ssh_user, ssh_auth_method, ssh_key_path)
            VALUES (:name, :path, :type, :ssh_host, :ssh_user, :ssh_auth_method, :ssh_key_path)
        ");

        $stmt->execute([
            ':name' => $config['name'],
            ':path' => $config['path'],
            ':type' => $config['type'] ?? 'local',
            ':ssh_host' => $config['ssh_host'] ?? null,
            ':ssh_user' => $config['ssh_user'] ?? null,
            ':ssh_auth_method' => $config['ssh_auth_method'] ?? null,
            ':ssh_key_path' => $config['ssh_key_path'] ?? null,
        ]);

        $id = (int)$this->db->lastInsertId();
        $this->exportBackup();

        return $id;
    }

    /**
     * Get all configured directories
     */
    public function getDirectories(): array
    {
        $stmt = $this->db->query("SELECT * FROM log_directories WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    /**
     * Returns directories with a 'valid' flag.
     * Local: checks if path exists and is readable.
     * SSH: checks if the SSH profile still exists in ConfigManager.
     */
    public function getValidDirectories(): array
    {
        $dirs = $this->getDirectories();
        $result = [];

        foreach ($dirs as $dir) {
            $valid = (($dir['type'] ?? 'local') === 'ssh')
                ? !empty($dir['ssh_host'])
                : (is_dir($dir['path']) && is_readable($dir['path']));

            $key = $dir['name'];

            $result[] = [
                'id' => $dir['id'],
                'key' => $key,
                'name' => $dir['name'],
                'path' => $dir['path'],
                'type' => $dir['type'] ?? 'local',
                'valid' => $valid,
            ];
        }

        return $result;
    }

    /**
     * Auto-cleanup on every page load:
     * - removes allowed_* auto-generated names
     * - removes duplicate paths (keeps lowest id)
     */
    public function cleanupAuto(): void
    {
        $this->db->exec("DELETE FROM log_directories WHERE name LIKE 'allowed_%'");

        $this->db->exec("
            DELETE FROM log_directories
            WHERE id NOT IN (
                SELECT MIN(id) FROM log_directories GROUP BY path
            )
        ");
    }

    /**
     * Get a specific directory by ID
     */
    public function getDirectory(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM log_directories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Update directory configuration
     */
    public function updateDirectory(int $id, array $config): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach (['name', 'path', 'type', 'ssh_host', 'ssh_user', 'ssh_auth_method', 'ssh_key_path', 'is_active'] as $field) {
            if (isset($config[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $config[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE log_directories SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        if ($result) {
            $this->exportBackup();
        }

        return $result;
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM log_directories WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);
        if ($result) {
            $this->exportBackup();
        }

        return $result;
    }

    /**
     * Remember a log file
     */
    public function rememberFile(int $directoryId, string $filePath): bool
    {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO log_files (directory_id, file_path, last_seen)
            VALUES (:directory_id, :file_path, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            ':directory_id' => $directoryId,
            ':file_path' => $filePath,
        ]);
    }

    /**
     * Get remembered files for a directory
     */
    public function getRememberedFiles(int $directoryId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM log_files
            WHERE directory_id = :directory_id
            ORDER BY last_seen DESC
        ");
        $stmt->execute([':directory_id' => $directoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Check if database has any configurations
     */
    public function hasConfigurations(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM log_directories WHERE is_active = 1");
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Default directories — source of truth for the 4 built-in entries.
     * Each entry has: key (user-friendly label), path (for backend resolution),
     * type (logical group), name (display name).
     *
     * @return array<int, array{key: string, path: string, type: string, name: string}>
     */
    public static function getDefaultDirectories(): array
    {
        return [
            ['key' => 'docker:/var/log',   'path' => '/var/log',       'type' => 'docker',     'name' => 'Kontener (Docker)'],
            ['key' => 'host:/var/log',     'path' => '/host/var/log',   'type' => 'host',       'name' => 'Host (Ubuntu)'],
            ['key' => 'host-home:~/logs',  'path' => '/host/home/logs', 'type' => 'home',       'name' => 'Host (~/logs)'],
            ['key' => 'repository:logs',   'path' => 'logs/',           'type' => 'repository', 'name' => 'Aplikacja (logs/)'],
        ];
    }

    /**
     * Export all directories to JSON backup file.
     * Called automatically after every write operation.
     * Sensitive fields (passwords, passphrases) are excluded from the backup.
     * The backup file is AES-256-GCM encrypted using BACKUP_ENCRYPTION_KEY from env.
     */
    private function exportBackup(): void
    {
        try {
            $dirs = $this->getDirectories();

            // Strip sensitive fields before writing to disk
            $sensitiveFields = ['ssh_password', 'ssh_key_passphrase', 'password'];
            $safeDirs = array_map(function (array $dir) use ($sensitiveFields): array {
                foreach ($sensitiveFields as $field) {
                    unset($dir[$field]);
                }

                return $dir;
            }, $dirs);

            $json = json_encode($safeDirs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $backupPath = dirname($this->dbPath).'/logviewer_backup.json';

            $encrypted = $this->encryptData($json);
            file_put_contents($backupPath, $encrypted);

            // Restrict file to owner read/write only
            chmod($backupPath, 0600);
        } catch (Exception $e) {
            error_log('LogConfig backup failed: '.$e->getMessage());
        }
    }

    /**
     * Restore directories from JSON backup when the database is empty.
     * Called automatically on construction after initSchema().
     */
    private function restoreFromBackupIfEmpty(): void
    {
        if ($this->hasConfigurations()) {
            return;
        }

        $backupPath = dirname($this->dbPath).'/logviewer_backup.json';
        if (!file_exists($backupPath)) {
            return;
        }

        $raw = file_get_contents($backupPath);
        if ($raw === false) {
            return;
        }

        // Try to decrypt; fall back to plain JSON for legacy unencrypted backups
        try {
            $json = $this->decryptData($raw);
        } catch (Exception $e) {
            error_log('LogConfig: backup decryption failed, trying plain JSON: '.$e->getMessage());
            $json = $raw;
        }

        $dirs = json_decode($json, true);
        if (!is_array($dirs)) {
            return;
        }

        foreach ($dirs as $dir) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM log_directories WHERE path = :path");
                $stmt->execute([':path' => $dir['path']]);
                if ($stmt->fetch()) {
                    continue;
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO log_directories (name, path, type, ssh_host, ssh_user, ssh_auth_method, ssh_key_path)
                    VALUES (:name, :path, :type, :ssh_host, :ssh_user, :ssh_auth_method, :ssh_key_path)
                "
                );
                $stmt->execute([
                    ':name' => $dir['name'],
                    ':path' => $dir['path'],
                    ':type' => $dir['type'] ?? 'local',
                    ':ssh_host' => $dir['ssh_host'] ?? null,
                    ':ssh_user' => $dir['ssh_user'] ?? null,
                    ':ssh_auth_method' => $dir['ssh_auth_method'] ?? null,
                    ':ssh_key_path' => $dir['ssh_key_path'] ?? null,
                ]);
            } catch (Exception $e) {
                error_log('LogConfig restore failed for '.($dir['name'] ?? '?').': '.$e->getMessage());
            }
        }
    }

    /**
     * Encrypt data using AES-256-GCM.
     * Output format: base64(iv . tag . ciphertext)
     *
     * @throws RuntimeException if encryption fails or key is missing
     */
    private function encryptData(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $cipher = 'aes-256-gcm';
        $ivLen = openssl_cipher_iv_length($cipher);
        $iv = random_bytes($ivLen);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException("(\$ciphertext === false) Encryption failed" . $this->getLastErrorMessage());
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Decrypt data encrypted by encryptData().
     *
     * @throws RuntimeException if decryption fails
     */
    private function decryptData(string $encoded): string
    {
        $key = $this->getEncryptionKey();
        $cipher = 'aes-256-gcm';
        $ivLen = openssl_cipher_iv_length($cipher);
        $tagLen = 16;

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= $ivLen + $tagLen) {
            throw new RuntimeException("(\$raw === false || strlen(\$raw) <= \$ivLen + \$tagLen) Invalid encrypted data (encoded length: " . (is_string($encoded) ? strlen($encoded) : 0) . ")" . $this->getLastErrorMessage());
        }

        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, $tagLen);
        $ciphertext = substr($raw, $ivLen + $tagLen);

        $plaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException("(\$plaintext === false) Decryption failed — wrong key or corrupted data" . $this->getLastErrorMessage());
        }

        return $plaintext;
    }

    /**
     * Load and validate the encryption key from environment.
     *
     * @throws RuntimeException if key is missing or invalid length
     */
    private function getEncryptionKey(): string
    {
        $hex = getenv('BACKUP_ENCRYPTION_KEY');
        if (empty($hex)) {
            throw new RuntimeException("(empty(\$hex)) BACKUP_ENCRYPTION_KEY is not set in environment");
        }

        $key = hex2bin($hex);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException("(\$key === false || strlen(\$key) !== 32) BACKUP_ENCRYPTION_KEY must be a 64-character hex string (32 bytes) (hex: " . (is_string($hex) ? $hex : 'null') . ")");
        }

        return $key;
    }
}
