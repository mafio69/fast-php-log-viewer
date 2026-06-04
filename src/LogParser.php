<?php

declare(strict_types=1);

namespace Mariusz\LogViewer;

/**
 * Parses log lines into structured arrays.
 *
 * Supported formats:
 *   fast-php-logger: [2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
 *   legacy multiline: 2026-04-30 08:43:43 --- DEBUG: { ... }
 *   php-errors: [04-May-2026 09:09:37 Europe/Warsaw] PHP Fatal error: message in file on line N
 */
class LogParser
{
    private const string PATTERN_FPL     = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const string PATTERN_LEGACY  = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';
    private const string PATTERN_PHP_ERR = '/^\[(?P<datetime>[^\]]+)\] PHP (?P<level>Parse error|Fatal error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error): (?P<message>.+?)(?:\s+in (?P<file>\S+) on line (?P<line>\d+))?\s*$/i';

    /** @return array<int, array{datetime: string, level: string, location: string, message: string, context: array}> */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        return $this->parseString($content);
    }

    /** @return array<int, array{datetime: string, level: string, location: string, message: string, context: array}> */
    public function parseString(string $content): array
    {
        $lines = explode("\n", $content);
        $entries = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // fast-php-logger single-line format
            if (preg_match(self::PATTERN_FPL, $line, $m)) {
                $entries[] = self::buildFplEntry($m);
                $i++;
                continue;
            }

            // PHP error log format: [04-May-2026 09:09:37 Europe/Warsaw] PHP Fatal error: ...
            if (preg_match(self::PATTERN_PHP_ERR, $line, $m)) {
                $levelMap = [
                    'fatal error' => 'ERROR', 'parse error' => 'ERROR',
                    'warning' => 'WARNING', 'notice' => 'NOTICE',
                    'deprecated' => 'NOTICE', 'strict standards' => 'NOTICE',
                    'catchable fatal error' => 'ERROR', 'recoverable fatal error' => 'ERROR',
                ];
                $level    = $levelMap[strtolower($m['level'])] ?? 'ERROR';
                $location = isset($m['file'], $m['line']) && $m['file'] !== '' ? $m['file'] . ':' . $m['line'] : '';

                // Collect stack trace continuation lines
                $j = $i + 1;
                $stackTrace = [];
                while ($j < $count && !preg_match('/^\[/', $lines[$j])) {
                    $stackTrace[] = $lines[$j];
                    $j++;
                }

                $context = [];
                if ($stackTrace) {
                    $context = ['stack_trace' => implode("\n", $stackTrace)];
                }

                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level'    => $level,
                    'location' => $location,
                    'message'  => $m['message'],
                    'context'  => $context,
                ];
                $i = $j;
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

                $decoded = json_decode(trim($rest), true, 512, JSON_THROW_ON_ERROR);
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

        return self::buildFplEntry($m);
    }

    /** @return array{datetime: string, level: string, location: string, message: string, context: array<mixed>} */
    private static function buildFplEntry(array $m): array
    {
        return [
            'datetime' => $m['datetime'],
            'level'    => strtoupper($m['level']),
            'location' => $m['location'],
            'message'  => $m['message'],
            'context'  => isset($m['context']) && $m['context'] !== ''
                ? (json_decode($m['context'], true, 512, JSON_THROW_ON_ERROR) ?? [])
                : [],
        ];
    }
}
