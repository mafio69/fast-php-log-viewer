<?php

declare(strict_types=1);

namespace Mariusz\LogViewer;

/**
 * Parses fast-php-logger log lines into structured arrays.
 *
 * Format: [2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
 */
class LogParser
{
    private const PATTERN = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';

    /** @return array<int, array{datetime: string, level: string, location: string, message: string, context: array<mixed>}> */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $entries = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $entry = $this->parseLine(rtrim($line));
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        fclose($handle);

        return array_reverse($entries);
    }

    /** @return array{datetime: string, level: string, location: string, message: string, context: array<mixed>}|null */
    public function parseLine(string $line): ?array
    {
        if (!preg_match(self::PATTERN, $line, $m)) {
            return null;
        }

        return [
            'datetime' => $m['datetime'],
            'level'    => strtoupper($m['level']),
            'location' => $m['location'],
            'message'  => $m['message'],
            'context'  => isset($m['context']) && $m['context'] !== ''
                ? (json_decode($m['context'], true) ?? [])
                : [],
        ];
    }
}
