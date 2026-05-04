<?php

declare(strict_types=1);

namespace Mariusz\LogViewer;

/**
 * Parses log lines into structured arrays.
 *
 * Supported formats:
 *   fast-php-logger: [2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
 *   legacy multiline: 2026-04-30 08:43:43 --- DEBUG: { ... }
 */
class LogParser
{
    private const PATTERN_FPL     = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const PATTERN_LEGACY  = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';

    /** @return array<int, array{datetime: string, level: string, location: string, message: string, context: array<mixed>}> */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // fast-php-logger single-line format
            if (preg_match(self::PATTERN_FPL, $line, $m)) {
                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level'    => strtoupper($m['level']),
                    'location' => $m['location'],
                    'message'  => $m['message'],
                    'context'  => isset($m['context']) && $m['context'] !== ''
                        ? (json_decode($m['context'], true) ?? [])
                        : [],
                ];
                $i++;
                continue;
            }

            // legacy multiline format: YYYY-MM-DD HH:MM:SS --- LEVEL: {json...}
            if (preg_match(self::PATTERN_LEGACY, $line, $m)) {
                $rest = $m['rest'];
                // collect continuation lines until JSON is complete
                $j = $i + 1;
                while ($j < $count && !preg_match(self::PATTERN_FPL, $lines[$j]) && !preg_match(self::PATTERN_LEGACY, $lines[$j])) {
                    $rest .= "\n" . $lines[$j];
                    $j++;
                }

                $context  = [];
                $message  = trim($rest);
                $location = '';

                $decoded = json_decode(trim($rest), true);
                if (is_array($decoded)) {
                    $context = $decoded;
                    // extract location from info field: LEVEL::Class::method::file::line
                    if (isset($decoded['info']) && preg_match('/::([^:]+)::(\d+)$/', $decoded['info'], $im)) {
                        $location = $im[1] . ':' . $im[2];
                    }
                    $message = $decoded['info'] ?? $m['level'];
                }

                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level'    => strtoupper($m['level']),
                    'location' => $location,
                    'message'  => $message,
                    'context'  => $context,
                ];
                $i = $j;
                continue;
            }

            $i++;
        }

        return array_reverse($entries);
    }

    /** @return array{datetime: string, level: string, location: string, message: string, context: array<mixed>}|null */
    public function parseLine(string $line): ?array
    {
        if (!preg_match(self::PATTERN_FPL, $line, $m)) {
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
