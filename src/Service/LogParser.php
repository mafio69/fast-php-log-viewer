<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Parses log lines into structured arrays.
 *
 * Supported formats:
 *   fast-php-logger: [2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
 *   simple format: [2024-06-05 12:00:00] INFO: Message
 *   legacy multiline: 2026-04-30 08:43:43 --- DEBUG: { ... }
 *   php-errors: [04-May-2026 09:09:37 Europe/Warsaw] PHP Fatal error: message in file on line N
 *   nginx error: 2026/06/05 07:00:00 [error] 1234#0: *12345 message
 *   apk log: Running `apk ...` at 2026-05-07 16:44:06 or (N/M) Installing package (version)
 */
class LogParser
{
    private const string PATTERN_FPL     = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const string PATTERN_SIMPLE  = '/^\[(?P<datetime>[^\]]+)\] (?P<level>[A-Z]+): (?P<message>.+)$/';
    private const string PATTERN_LEGACY  = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';
    private const string PATTERN_PHP_ERR = '/^\[(?P<datetime>[^\]]+)\] PHP (?P<level>Parse error|Fatal error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error): (?P<message>.+?)(?:\s+in (?P<file>\S+) on line (?P<line>\d+))?\s*$/i';
    private const string PATTERN_NGINX_ERR = '/^(?P<datetime>\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(?P<level>error|warn|notice|info|crit|alert|emerg)\] (?P<pid>\d+)#\d+: \*(?P<tid>\d+) (?P<message>.+)$/i';
    private const string PATTERN_APK_LOG = '/^Running `apk (?P<message>.+)` at (?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/';
    private const string PATTERN_APK_INSTALL = '/^\((?P<num>\d+)\/(?P<total>\d+)\) (?P<action>Installing|Purging) (?P<message>.+)$/';
    private const string PATTERN_APK_WARNING = '/^WARNING: (?P<message>.+)$/';
    private const string PATTERN_APK_OK = '/^OK: (?P<message>.+)$/';
    private const string PATTERN_APK_EXEC = '/^Executing (?P<message>.+)$/';
    private const string PATTERN_APK_TRIGGER = '/^Executing (?P<message>.+)\.trigger$/';

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

            // nginx error log format: 2026/06/05 07:00:00 [error] 1234#0: *12345 message
            if (preg_match(self::PATTERN_NGINX_ERR, $line, $m)) {
                $levelMap = [
                    'error' => 'ERROR', 'warn' => 'WARNING', 'warning' => 'WARNING',
                    'notice' => 'NOTICE', 'info' => 'INFO', 'crit' => 'CRITICAL',
                    'alert' => 'ALERT', 'emerg' => 'EMERGENCY',
                ];
                $level = $levelMap[strtolower($m['level'])] ?? strtoupper($m['level']);

                $entries[] = [
                    'datetime' => str_replace('/', '-', $m['datetime']),
                    'level' => $level,
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // fast-php-logger single-line format
            if (preg_match(self::PATTERN_FPL, $line, $m)) {
                $entries[] = self::buildFplEntry($m);
                $i++;
                continue;
            }

            // Simple format: [2024-06-05 12:00:00] INFO: Message
            if (preg_match(self::PATTERN_SIMPLE, $line, $m)) {
                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level' => strtoupper($m['level']),
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
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

            // Alpine APK log format: Running `apk ...` at 2026-05-07 16:44:06
            if (preg_match(self::PATTERN_APK_LOG, $line, $m)) {
                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level' => 'INFO',
                    'location' => '',
                    'message' => 'Running apk: ' . $m['message'],
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // Alpine APK install/purge: (N/M) Installing package (version)
            if (preg_match(self::PATTERN_APK_INSTALL, $line, $m)) {
                $level = $m['action'] === 'Installing' ? 'INFO' : 'INFO';
                $entries[] = [
                    'datetime' => '', // No timestamp in this line
                    'level' => $level,
                    'location' => '',
                    'message' => "({$m['num']}/{$m['total']}) {$m['action']} {$m['message']}",
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // Alpine APK warnings
            if (preg_match(self::PATTERN_APK_WARNING, $line, $m)) {
                $entries[] = [
                    'datetime' => '',
                    'level' => 'WARNING',
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // Alpine APK OK messages
            if (preg_match(self::PATTERN_APK_OK, $line, $m)) {
                $entries[] = [
                    'datetime' => '',
                    'level' => 'INFO',
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // Alpine APK Executing messages
            if (preg_match(self::PATTERN_APK_EXEC, $line, $m)) {
                $entries[] = [
                    'datetime' => '',
                    'level' => 'INFO',
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
                $i++;
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
