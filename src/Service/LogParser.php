<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use RuntimeException;

/**
 * Parses log lines into structured arrays.
 *
 * Supported formats:
 *   fast-php-logger: [2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
 *   simple format: [2024-06-05 12:00:00] INFO: Message
 *   legacy multiline: 2026-04-30 08:43:43 --- DEBUG: { ... }
 *   php-errors: [04-May-2026 09:09:37 Europe/Warsaw] PHP Fatal error: message in file on line N
 *   nginx error: 2026/06/05 07:00:00 [error] 1234#0: *12345 message
 *   nginx access: 192.168.1.1 - - [05/Jun/2026:09:00:00 +0000] "GET / HTTP/1.1" 200 1234
 *   apk log: Running `apk ...` at 2026-05-07 16:44:06 or (N/M) Installing package (version)
 *   syslog: Jun  9 20:24:01 hostname process[pid]: message
 *   apt/bootstrap: 2024-08-27 15:37:02 URL:http://... -> ...
 *   systemd journal: 2026-06-07T10:44:33.740726+00:00 hostname process[pid]: message
 */
class LogParser
{
    use ErrorContextTrait;

    private const PATTERN_FPL     = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const PATTERN_SIMPLE  = '/^\[(?P<datetime>[^\]]+)\] (?P<level>[A-Z]+): (?P<message>.+)$/';
    private const PATTERN_LEGACY  = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';
    private const PATTERN_PHP_ERR = '/^\[(?P<datetime>[^\]]+)\] PHP (?P<level>Parse error|Fatal error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error): (?P<message>.+?)(?:\s+in (?P<file>\S+) on line (?P<line>\d+))?\s*$/i';
    private const PATTERN_NGINX_ERR = '/^(?P<datetime>\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(?P<level>error|warn|notice|info|crit|alert|emerg)\] (?P<pid>\d+)#\d+: \*(?P<tid>\d+) (?P<message>.+)$/i';
    private const PATTERN_NGINX_ACCESS = '/^(?P<ip>[\d\.]+) - - \[(?P<datetime>[^\]]+)\] "(?P<method>\w+) (?P<path>[^\s]+) HTTP\/[\d\.]+" (?P<status>\d+) (?P<size>\d+)/';
    private const PATTERN_APK_LOG = '/^Running `apk (?P<message>.+)` at (?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/';
    private const PATTERN_APK_INSTALL = '/^\((?P<num>\d+)\/(?P<total>\d+)\) (?P<action>Installing|Purging) (?P<message>.+)$/';
    private const PATTERN_APK_WARNING = '/^WARNING: (?P<message>.+)$/';
    private const PATTERN_APK_OK = '/^OK: (?P<message>.+)$/';
    private const PATTERN_APK_EXEC = '/^Executing (?P<message>.+)$/';
    private const PATTERN_SYSLOG   = '/^(?P<month>\w{3})\s+(?P<day>\d{1,2})\s+(?P<time>\d{2}:\d{2}:\d{2})\s+(?P<hostname>\S+)\s+(?P<process>\S+?)(?:\[(?P<pid>\d+)\])?:\s+(?P<message>.+)$/';
    private const PATTERN_APT_LOG = '/^(?P<datetime>\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(?P<message>.+)$/';
    private const PATTERN_SYSTEMD_JOURNAL = '/^(?P<datetime>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+[+-]\d{2}:\d{2})\s+(?P<hostname>\S+)\s+(?P<process>\S+?)(?:\[(?P<pid>\d+)\])?:\s+(?P<message>.+)$/';

    /** @return array<int, array{datetime: string, level: string, location: string, message: string, context: array}> */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("(\$content === false) Failed to read log file: $path (path: $path)" . $this->getLastErrorMessage());
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

            // syslog format: Jun  9 20:24:01 hostname process[pid]: message
            if (preg_match(self::PATTERN_SYSLOG, $line, $m)) {
                $monthMap = [
                    'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
                    'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
                ];
                $month = $monthMap[$m['month']] ?? '01';
                $day = str_pad($m['day'], 2, '0', STR_PAD_LEFT);
                $datetime = date('Y') . '-' . $month . '-' . $day . ' ' . $m['time'];
                $location = $m['hostname'] . ' ' . $m['process'] . (isset($m['pid']) ? '[' . $m['pid'] . ']' : '');

                // Try to detect log level from message
                $messageLower = strtolower($m['message']);
                $level = 'INFO';
                if (str_contains($messageLower, 'error') || str_contains($messageLower, 'failed')) {
                    $level = 'ERROR';
                } elseif (str_contains($messageLower, 'warning') || str_contains($messageLower, 'warn')) {
                    $level = 'WARNING';
                } elseif (str_contains($messageLower, 'debug')) {
                    $level = 'DEBUG';
                } elseif (str_contains($messageLower, 'critical') || str_contains($messageLower, 'fatal')) {
                    $level = 'CRITICAL';
                }

                $entries[] = [
                    'datetime' => $datetime,
                    'level' => $level,
                    'location' => $location,
                    'message' => $m['message'],
                    'context' => []
                ];
                $i++;
                continue;
            }

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
                    'context' => []
                ];
                $i++;
                continue;
            }

            // nginx access log format: 192.168.1.1 - - [05/Jun/2026:09:00:00 +0000] "GET / HTTP/1.1" 200 1234
            if (preg_match(self::PATTERN_NGINX_ACCESS, $line, $m)) {
                // Convert datetime from 05/Jun/2026:09:00:00 +0000 to 2026-06-05 09:00:00
                $datetime = preg_replace('/^(\d{2})\/(\w{3})\/(\d{4}):(\d{2}:\d{2}:\d{2}).*/', '$3-$2-$1 $4', $m['datetime']);
                $datetime = str_replace(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'], $datetime);

                $level = $m['status'] >= 400 ? 'ERROR' : ($m['status'] >= 300 ? 'WARNING' : 'INFO');
                $message = sprintf('%s %s - %s %s', $m['method'], $m['path'], $m['status'], $m['size']);

                $entries[] = [
                    'datetime' => $datetime,
                    'level' => $level,
                    'location' => $m['ip'],
                    'message' => $message,
                    'context' => []
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
                    'context' => []
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
                    'context' => []
                ];
                $i++;
                continue;
            }

            // Alpine APK install/purge: (N/M) Installing package (version)
            if (preg_match(self::PATTERN_APK_INSTALL, $line, $m)) {
                $level = 'INFO';
                $entries[] = [
                    'datetime' => '', // No timestamp in this line
                    'level' => $level,
                    'location' => '',
                    'message' => "({$m['num']}/{$m['total']}) {$m['action']} {$m['message']}",
                    'context' => []
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
                    'context' => []
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
                    'context' => []
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
                    'context' => []
                ];
                $i++;
                continue;
            }

            // APT/bootstrap log format: 2024-08-27 15:37:02 URL:http://... -> ...
            if (preg_match(self::PATTERN_APT_LOG, $line, $m)) {
                // Try to detect log level from message
                $messageLower = strtolower($m['message']);
                $level = 'INFO';
                if (str_contains($messageLower, 'error') || str_contains($messageLower, 'failed')) {
                    $level = 'ERROR';
                } elseif (str_contains($messageLower, 'warning') || str_contains($messageLower, 'warn')) {
                    $level = 'WARNING';
                }

                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level' => $level,
                    'location' => '',
                    'message' => $m['message'],
                    'context' => [],
                ];
                $i++;
                continue;
            }

            // Systemd journal format: 2026-06-07T10:44:33.740726+00:00 hostname process[pid]: message
            if (preg_match(self::PATTERN_SYSTEMD_JOURNAL, $line, $m)) {
                // Convert ISO 8601 to simple format: 2026-06-07T10:44:33.740726+00:00 -> 2026-06-07 10:44:33
                $datetime = preg_replace('/T(\d{2}:\d{2}:\d{2})\.\d+[+-]\d{2}:\d{2}/', ' $1', $m['datetime']);
                $location = $m['hostname'].' '.$m['process'].(isset($m['pid']) ? '['.$m['pid'].']' : '');

                // Try to detect log level from message
                $messageLower = strtolower($m['message']);
                $level = 'INFO';
                if (str_contains($messageLower, 'error') || str_contains($messageLower, 'failed')) {
                    $level = 'ERROR';
                } elseif (str_contains($messageLower, 'warning') || str_contains($messageLower, 'warn')) {
                    $level = 'WARNING';
                }

                $entries[] = [
                    'datetime' => $datetime,
                    'level' => $level,
                    'location' => $location,
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
