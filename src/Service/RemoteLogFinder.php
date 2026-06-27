<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Finds log files on remote servers via SSH.
 */
class RemoteLogFinder
{
    use ErrorContextTrait;

    private SSH $ssh;

    public function __construct(SSH $ssh)
    {
        $this->ssh = $ssh;
    }

    /**
     * Find all log files in remote directory
     */
    public function findAll(string $remotePath, bool $allFiles = false): array
    {
        if (!$this->ssh->directoryExists($remotePath)) {
            return [];
        }

        $files = [];

        if ($allFiles) {
            // List all files without pattern filtering
            $command = sprintf('find %s -maxdepth 1 -type f 2>/dev/null', escapeshellarg($remotePath));
            $output = $this->ssh->exec($command);

            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (!empty($line) && $this->ssh->fileExists($line)) {
                    $files[] = [
                        'path' => $line,
                        'name' => basename($line),
                        'size' => $this->ssh->fileSize($line),
                    ];
                }
            }
        } else {
            // Try common log patterns
            $patterns = [
                '*.log',
                '*error*',
                '*debug*',
                '*access*',
                '*.php',
                '*.txt',
                'messages',
                'syslog',
                'btmp',
                'wtmp',
                'lastlog',
                '*.out',
                '*.err',
            ];

            foreach ($patterns as $pattern) {
                $command = sprintf('find %s -maxdepth 3 -name "%s" -type f 2>/dev/null', escapeshellarg($remotePath), $pattern);
                $output = $this->ssh->exec($command);

                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (!empty($line) && $this->ssh->fileExists($line)) {
                        $files[] = [
                            'path' => $line,
                            'name' => basename($line),
                            'size' => $this->ssh->fileSize($line),
                        ];
                    }
                }
            }
        }

        // Remove duplicates
        $files = array_values(array_unique($files, SORT_REGULAR));

        // Sort by name
        usort($files, fn($a, $b) => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * Scan common remote log directories
     */
    public function scanCommonDirectories(): array
    {
        $commonPaths = [
            '/var/log',
            '/var/www/html/logs',
            '/home/*/logs',
            '/opt/logs',
        ];

        $foundDirs = [];

        foreach ($commonPaths as $path) {
            if ($this->ssh->directoryExists($path)) {
                $files = $this->findAll($path);
                if (!empty($files)) {
                    $foundDirs[$path] = [
                        'path' => $path,
                        'name' => basename($path),
                        'file_count' => count($files),
                        'files' => array_slice($files, 0, 5),
                    ];
                }
            }
        }

        return $foundDirs;
    }
}