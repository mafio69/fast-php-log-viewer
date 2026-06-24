<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use RuntimeException;

/**
 * Scans directories for log files automatically.
 */
class LogScanner
{
    private array $commonLogPaths = [
        '/var/log',
        '/var/www/html/logs',
        '/var/log/apache2',
        '/var/log/nginx',
        '/var/log/php-fpm',
        '/tmp',
        './logs',
        '../logs',
    ];

    private array $logExtensions = [
        '.log', '.txt', '.php', '.error', '.debug', '.info',
        '.warn', '.warning', '.err', '.crit', '.emerg',
    ];

    private array $logPatterns = [
        '*error*', '*debug*', '*access*', '*access*',
        '*php*', '*apache*', '*nginx*', '*fpm*',
    ];

    private function getLastErrorMessage(): string
    {
        $error = error_get_last();
        if ($error === null) {
            return '';
        }
        return sprintf(' [PHP Error: %s in %s:%d]', $error['message'], $error['file'], $error['line']);
    }

    /**
     * Scan common log directories for log files
     */
    public function scanCommonDirectories(): array
    {
        $foundDirs = [];

        foreach ($this->commonLogPaths as $path) {
            if (is_dir($path) && is_readable($path)) {
                $files = $this->scanDirectory($path);
                if (!empty($files)) {
                    $foundDirs[$path] = [
                        'path' => $path,
                        'name' => basename($path),
                        'type' => 'local',
                        'file_count' => count($files),
                        'files' => array_slice($files, 0, 10), // First 10 files
                    ];
                }
            }
        }

        return $foundDirs;
    }

    /**
     * Scan a specific directory for log files
     */
    public function scanDirectory(string $path): array
    {
        if (!is_dir($path) || !is_readable($path)) {
            return [];
        }

        $files = [];

        // Scan with glob for common patterns
        foreach ($this->logPatterns as $pattern) {
            $matches = glob($path . '/' . $pattern);
            if ($matches) {
                foreach ($matches as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $files[] = $this->getFileInfo($file);
                    }
                }
            }
        }

        // Also check specific extensions
        foreach ($this->logExtensions as $ext) {
            $matches = glob($path . '/*' . $ext);
            if ($matches) {
                foreach ($matches as $file) {
                    if (is_file($file) && is_readable($file) && !isset($files[$file])) {
                        $files[] = $this->getFileInfo($file);
                    }
                }
            }
        }

        // Remove duplicates and sort by modification time
        $files = array_values(array_unique($files, SORT_REGULAR));
        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    /**
     * Get file information
     */
    private function getFileInfo(string $path): array
    {
        $size = @filesize($path);
        if ($size === false) {
            throw new RuntimeException("(@filesize(\$path) === false) Failed to get filesize for: $path (path: $path)" . $this->getLastErrorMessage());
        }

        $mtime = @filemtime($path);
        if ($mtime === false) {
            throw new RuntimeException("(@filemtime(\$path) === false) Failed to get mtime for: $path (path: $path)" . $this->getLastErrorMessage());
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'size' => $size,
            'mtime' => $mtime,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    /**
     * Check if a file is likely a log file
     */
    public function isLogFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = strtolower(basename($path));

        // Check extension
        if (in_array('.' . $ext, $this->logExtensions)) {
            return true;
        }

        // Check filename patterns
        foreach ($this->logPatterns as $pattern) {
            $pattern = str_replace('*', '.*', $pattern);
            if (preg_match('/' . $pattern . '/i', $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Docker container log paths
     */
    public function getDockerLogPaths(): array
    {
        $dockerPaths = [];

        if (file_exists('/.dockerenv')) {
            $dockerPaths[] = '/var/log/supervisor';
            $dockerPaths[] = '/var/log/php-fpm';
        }

        $dockerMounts = [
            '/var/log/docker',
            '/docker/logs',
            '/container/logs',
        ];

        foreach ($dockerMounts as $mount) {
            if (is_dir($mount) && is_readable($mount)) {
                $dockerPaths[] = $mount;
            }
        }

        return $dockerPaths;
    }

}