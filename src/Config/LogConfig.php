<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Config;

use Exception;
use PDO;
use PDOException;

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
            throw new \RuntimeException('Failed to connect to SQLite: ' . $e->getMessage());
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
            throw new Exception('Directory already exists: ' . $config['path']);
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

        return (int) $this->db->lastInsertId();
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
     * Remove duplicate directories (keep first occurrence)
     */
    public function removeDuplicates(): int
    {
        $this->db->exec("
            DELETE FROM log_directories
            WHERE id NOT IN (
                SELECT MIN(id) FROM log_directories GROUP BY path
            )
        ");
        return $this->db->exec("DELETE FROM log_directories WHERE path IN (SELECT path FROM log_directories GROUP BY path HAVING COUNT(*) > 1) AND id NOT IN (SELECT MIN(id) FROM log_directories GROUP BY path)");
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
        return $stmt->execute($params);
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM log_directories WHERE id = :id");
        return $stmt->execute([':id' => $id]);
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
     * Add default local directories for first run
     */
    public function addDefaultDirectories(): void
    {
        if ($this->hasConfigurations()) {
            return;
        }

        $scanner = new LogScanner();
        $foundDirs = $scanner->scanCommonDirectories();

        foreach ($foundDirs as $path => $info) {
            try {
                $this->addDirectory([
                    'name' => $info['name'],
                    'path' => $path,
                    'type' => 'local',
                ]);
            } catch (\Exception $e) {
                // Skip if directory cannot be added
            }
        }

        // If no directories found, add basic defaults
        if (empty($foundDirs)) {
            $basicDefaults = [
                ['name' => 'Local Logs', 'path' => __DIR__ . '/../logs', 'type' => 'local'],
            ];

            foreach ($basicDefaults as $config) {
                if (is_dir($config['path'])) {
                    try {
                        $this->addDirectory($config);
                    } catch (\Exception $e) {
                        // Skip if directory cannot be added
                    }
                }
            }
        }
    }
}