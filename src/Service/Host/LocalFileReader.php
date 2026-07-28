<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Host;

use RuntimeException;

/**
 * Single responsibility: read the raw contents of one local log file as text.
 *
 * It does not know about log line formats — that is LogParser::parseString's
 * job. Splitting "read bytes from disk" from "interpret bytes as log lines"
 * is what lets the same parser work on strings arriving from Docker exec or
 * SSH SFTP, without every transport growing its own mini-parser inline.
 *
 * Throwing on unreadable content (instead of silently returning empty
 * string) is intentional: callers like LogController::getEntries cannot
 * distinguish "empty log" from "permission denied" with the silent variant,
 * which was the source of the "pusty plik = file_not_found" bug documented
 * in docs/technical.md §10.1 for the Docker path.
 */
final class LocalFileReader
{
    public function read(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("file_not_found: $filePath");
        }
        if (!is_readable($filePath)) {
            throw new RuntimeException("file_not_readable: $filePath");
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("read_failed: $filePath");
        }

        return $content;
    }
}
