<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

class SecurityService
{
    /**
     * Sanitizes a filename to prevent path traversal.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Allow only alphanumeric, dots, underscores and dashes
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Limit length
        $filename = substr($filename, 0, 255);

        // Ensure it's not empty
        return empty($filename) ? 'file.log' : $filename;
    }

    /**
     * Checks if a string contains binary content.
     */
    public function isBinaryContent(string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        // Check for null bytes or a high percentage of non-printable characters
        if (str_contains($content, "\0")) {
            return true;
        }

        $nonPrintable = 0;
        $len = strlen($content);
        $sampleSize = min($len, 1024);

        for ($i = 0; $i < $sampleSize; $i++) {
            $ord = ord($content[$i]);
            // Allow common whitespace: space (32), tab (9), newline (10), carriage return (13)
            if ($ord < 32 && !in_array($ord, [9, 10, 13])) {
                $nonPrintable++;
            }
        }

        return ($nonPrintable / $sampleSize) > 0.1;
    }

    /**
     * Checks if a string appears to be a valid text log file.
     */
    public function isValidTextFile(string $content): bool
    {
        if (empty($content)) {
            return true;
        }

        if ($this->isBinaryContent($content)) {
            return false;
        }

        // Basic heuristic: check for common log patterns
        $patterns = [
            '/^\[\d{4}-\d{2}-\d{2}/', // [2026-06-20
            '/^\d{4}-\d{2}-\d{2}/',   // 2026-06-20
            '/^\d{2}:\d{2}:\d{2}/',   // 12:34:56
            '/\[(INFO|DEBUG|ERROR|WARNING|NOTICE)\]/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        // If no patterns match, but it's not binary, it's probably okay if it's mostly printable
        return true;
    }

    /**
     * Checks if a string contains suspicious content (e.g. PHP tags, script tags).
     */
    public function containsSuspiciousContent(string $content): bool
    {
        $suspicious = [
            '<?php', '<?=', '?>',
            '<script', '</script>',
            'javascript:',
            'eval(', 'exec(', 'system(', 'passthru(', 'shell_exec(',
            '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION', '$_ENV', '$_SERVER',
            '#!/usr/bin', '#!/bin/sh', '#!/bin/bash',
        ];

        foreach ($suspicious as $token) {
            if (stripos($content, $token) !== false) {
                return true;
            }
        }

        return false;
    }
}
