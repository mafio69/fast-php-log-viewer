<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Host;

use Psr\Log\LoggerInterface;

/**
 * Single responsibility: read the file list of one host directory.
 *
 * Replaces the older GlobLogFinder. Two changes vs the previous version:
 *
 *   1. Lives under Service/Host/ so the Host/Docker/Ssh domain split is
 *      visible in the filesystem.
 *   2. Drops the glob('*.php') branch. Reading .php files as "logs" was a
 *      leftover from a demo and risked exposing source code (including
 *      config files) through the log viewer. Host log readers should match
 *      log-like extensions only; if a user genuinely wants to inspect a
 *      .php file they should add the directory explicitly and use
 *      LocalFileReader, not the directory-listing API.
 */
class LocalDirectoryReader
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array<int, array{file: string, date: string, size: int}>
     */
    public function findAll(string $path): array
    {
        $this->logger?->debug('LocalDirectoryReader::findAll called', ['path' => $path, 'is_dir' => is_dir($path)]);

        if (!is_dir($path)) {
            $this->logger?->debug('LocalDirectoryReader::findAll dir not found', ['path' => $path]);
            return [];
        }

        $logFiles = glob($path . '/*.log') ?: [];

        $this->logger?->debug('LocalDirectoryReader::findAll glob results', [
            'path' => $path,
            'total' => count($logFiles),
        ]);

        $files = [];
        foreach ($logFiles as $filePath) {
            $mtime = $this->safeFilemtime($filePath);
            $size = $this->safeFilesize($filePath);
            $files[] = [
                'file' => basename($filePath),
                'date' => date('Y-m-d H:i:s', $mtime),
                'size' => $size,
            ];
        }

        $this->logger?->debug('LocalDirectoryReader::findAll result', ['path' => $path, 'count' => count($files)]);

        return $files;
    }

    private function safeFilemtime(string $filePath): int
    {
        try {
            $mtime = filemtime($filePath);
            if ($mtime === false) {
                $this->logger?->warning('LocalDirectoryReader: filemtime failed', ['path' => $filePath]);
                return time();
            }
            return $mtime;
        } catch (\Throwable $e) {
            $this->logger?->warning('LocalDirectoryReader: filemtime exception', ['path' => $filePath, 'error' => $e->getMessage()]);
            return time();
        }
    }

    private function safeFilesize(string $filePath): int
    {
        try {
            $size = filesize($filePath);
            if ($size === false) {
                $this->logger?->warning('LocalDirectoryReader: filesize failed', ['path' => $filePath]);
                return 0;
            }
            return $size;
        } catch (\Throwable $e) {
            $this->logger?->warning('LocalDirectoryReader: filesize exception', ['path' => $filePath, 'error' => $e->getMessage()]);
            return 0;
        }
    }
}
