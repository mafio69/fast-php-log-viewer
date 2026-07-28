<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Host;

use Mariusz\LogViewer\Service\LogFinderInterface;
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
 *
 * Still implements the original LogFinderInterface for the AppBootstrap
 * library API; the deprecated alias will be retired in G02.
 */
final class LocalDirectoryReader implements LogFinderInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

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
            $files[] = [
                'file' => basename($filePath),
                'date' => date('Y-m-d H:i:s', @filemtime($filePath) ?: time()),
                'size' => @filesize($filePath) ?: 0,
            ];
        }

        $this->logger?->debug('LocalDirectoryReader::findAll result', ['path' => $path, 'count' => count($files)]);

        return $files;
    }
}
