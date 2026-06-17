<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Finds log files produced by fast-php-logger (default structure: logDir/Y/m/Y-m-d.log).
 */
readonly class LogFinder
{
    public function __construct(private string $logDir)
    {
    }

    /**
     * Returns all log files sorted newest first.
     *
     * @return array<int, array{path: string, date: string, size: int}>
     */
    public function findAll(): array
    {
        $dir   = self::normalizePath($this->logDir);
        $files = array_unique(array_merge(
            glob($dir . '/*/*/*.log') ?: [],
            glob($dir . '/*/*.log') ?: [],
            glob($dir . '/*.log') ?: [],
            glob($dir . '/*/*/*.php') ?: [],
            glob($dir . '/*/*.php') ?: [],
            glob($dir . '/*.php') ?: [],
        ));

        $result = [];
        foreach ($files as $path) {
            $result[] = [
                'path' => self::normalizePath($path),
                'date' => $this->extractDate($path),
                'size' => filesize($path) ?: 0,
            ];
        }

        usort($result, static fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $result;
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // Remove double slashes and trailing slash
        $path = preg_replace('/\/+/', '/', $path);

        return rtrim($path, '/');
    }

    private function extractDate(string $path): string
    {
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $name = basename($path, '.' . $ext);
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $name, $m)) {
            return $m[1];
        }

        return date('Y-m-d', filemtime($path) ?: time());
    }
}
