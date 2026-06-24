<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use Psr\Log\LoggerInterface;

class GlobLogFinder implements LogFinderInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function findAll(string $path): array
    {
        $this->logger?->debug('GlobLogFinder::findAll called', ['path' => $path, 'is_dir' => is_dir($path)]);

        if (!is_dir($path)) {
            $this->logger?->debug('GlobLogFinder::findAll dir not found', ['path' => $path]);
            return [];
        }

        // Używamy glob do znalezienia plików .log i .php
        $logFiles = glob($path.'/*.log') ?: [];
        $phpFiles = glob($path.'/*.php') ?: [];
        $allFilePaths = array_merge($logFiles, $phpFiles);

        $this->logger?->debug('GlobLogFinder::findAll glob results', [
            'path' => $path,
            'total' => count($allFilePaths),
        ]);

        $files = [];
        foreach ($allFilePaths as $filePath) {
            $files[] = [
                'file' => basename($filePath),
                'date' => date('Y-m-d H:i:s', @filemtime($filePath) ?: time()),
                'size' => @filesize($filePath) ?: 0,
            ];
        }

        $this->logger?->debug('GlobLogFinder::findAll result', ['path' => $path, 'count' => count($files)]);

        return $files;
    }
}