<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

class SecurityService
{
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
