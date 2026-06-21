<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

class GlobLogFinder implements LogFinderInterface
{
    public function findAll(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        // Używamy glob do znalezienia plików .log i .php
        $allFilePaths = array_merge(
            glob($path . '/*.log') ?: [],
            glob($path . '/*.php') ?: []
        );

        $files = [];
        foreach ($allFilePaths as $filePath) {
            $files[] = [
                'file' => basename($filePath),
                'date' => date('Y-m-d H:i:s', @filemtime($filePath) ?: time()),
                'size' => @filesize($filePath) ?: 0,
            ];
        }

        return $files;
    }
}