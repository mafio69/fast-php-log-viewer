<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

class LogFinder
{
    private string $logDir;

    public function __construct(string $logDir)
    {
        $this->logDir = $logDir;
    }

    private function getLastErrorMessage(): string
    {
        $error = error_get_last();
        if ($error === null) {
            return '';
        }
        return sprintf(' [PHP Error: %s in %s:%d]', $error['message'], $error['file'], $error['line']);
    }

    public function findAll(): array
    {
        $dir   = self::normalizePath($this->logDir);
        $files = array_unique(array_merge(
            glob($dir . '/*/*/*.log') ?: [],
            glob($dir . '/*/*.log')   ?: [],
            glob($dir . '/*.log')     ?: [],
            glob($dir . '/*/*/*.php') ?: [],
            glob($dir . '/*/*.php')   ?: [],
            glob($dir . '/*.php')     ?: [],
        ));

        $result = [];
        foreach ($files as $path) {
            $size = @filesize($path);
            if ($size === false) {
                throw new \RuntimeException("(@filesize(\$path) === false) Failed to get filesize for: $path (path: $path)" . $this->getLastErrorMessage());
            }

            $result[] = [
                'path' => self::normalizePath($path),
                'date' => $this->extractDate($path),
                'size' => $size,
            ];
        }

        usort($result, static fn($a, $b) => strcmp($b['date'], $a['date']));

        return $result;
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
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

        $mtime = @filemtime($path);
        if ($mtime === false) {
            throw new \RuntimeException("(@filemtime(\$path) === false) Failed to get mtime for: $path (path: $path)" . $this->getLastErrorMessage());
        }

        return date('Y-m-d', $mtime);
    }
}
