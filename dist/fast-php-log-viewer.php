<?php
/**
 * fast-php-log-viewer — single-file drop-in
 * https://github.com/mafio69/fast-php-log-viewer
 *
 * Usage (no Composer needed):
 *   1. Copy this file to your project
 *   2. Set LOG_DIR to your logs directory
 *   3. Open in browser
 *
 * @version 1.0.0
 */

declare(strict_types=1);

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}

namespace Mariusz\LogViewer {

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
    private const PATTERN_FPL     = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const PATTERN_LEGACY  = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';
    private const PATTERN_PHP_ERR = '/^\[(?P<datetime>[^\]]+)\] PHP (?P<level>Parse error|Fatal error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error): (?P<message>.+?)(?:\s+in (?P<file>[^\s]+) on line (?P<line>\d+))?\s*$/i';

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
                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level'    => $level,
                    'location' => $location,
                    'message'  => $m['message'],
                    'context'  => [],
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

/**
 * Finds log files produced by fast-php-logger (default structure: logDir/Y/m/Y-m-d.log).
 */
class LogFinder
{
    public function __construct(private readonly string $logDir) {}

    /**
     * Returns all log files sorted newest first.
     *
     * @return array<int, array{path: string, date: string, size: int}>
     */
    public function findAll(): array
    {
        $dir   = self::normalizePath($this->logDir);
        $files = array_unique(array_merge(
            glob($dir . '/*/*/*.log') ?: [],
            glob($dir . '/*/*.log')   ?: [],
            glob($dir . '/*.log')     ?: [],
            glob($dir . '/*/*/*.php') ?: [],
            glob($dir . '/*/*.php')   ?: [],
            glob($dir . '/*.php')     ?: [],
        ));

        $result = [];
        foreach ($files as $path) {
            $result[] = [
                'path' => self::normalizePath($path),
                'date' => $this->extractDate($path),
                'size' => filesize($path) ?: 0,
            ];
        }

        usort($result, fn($a, $b) => strcmp($b['date'], $a['date']));

        return $result;
    }

    public static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function extractDate(string $path): string
    {
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $name = basename($path, '.' . $ext);
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $name, $m)) {
            return $m[1];
        }

        return date('Y-m-d', filemtime($path) ?: time());
    }
}

}

namespace {

use Mariusz\LogViewer\LogFinder;
use Mariusz\LogViewer\LogParser;

if (isset($_GET['action'])) {
/**
 * fast-php-log-viewer API endpoint.
 *
 * GET ?action=files              → list of log files
 * GET ?action=entries&file=path  → parsed entries from a file
 *
 * Configure LOG_DIR before including or set it as a constant.
 */

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: dirname(__DIR__) . '/logs');
}

// When installed as a Composer dependency the autoloader is already loaded
// by the entry point (viewer/index.php). The original relative path
// __DIR__ . '/../vendor/autoload.php' resolves to the package's own vendor/
// which doesn't exist — only require if the file actually exists.
$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}
unset($_autoload);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'files'   => respondFiles(),
        'entries' => respondEntries(),
        default   => respondError('Unknown action', 400),
    };
} catch (\Throwable $e) {
    respondError($e->getMessage(), 500);
}

function respondFiles(): void
{
    $finder = new LogFinder(LOG_DIR);
    $files  = $finder->findAll();

    echo json_encode(array_map(fn($f) => [
        'file' => $f['path'],
        'date' => $f['date'],
        'size' => $f['size'],
    ], $files));
}

function respondEntries(): void
{
    $file = $_GET['file'] ?? '';

    if ($file === '') {
        respondError('Missing file parameter', 400);
        return;
    }

    // Security: file must be inside LOG_DIR
    $real    = realpath($file);
    $logReal = realpath(LOG_DIR);

    // Normalize separators for Windows/WSL path compatibility
    $real    = $real    !== false ? str_replace('\\', '/', $real)    : str_replace('\\', '/', $file);
    $logReal = $logReal !== false ? str_replace('\\', '/', $logReal) : str_replace('\\', '/', LOG_DIR);

    if (!str_starts_with($real, rtrim($logReal, '/') . '/')) {
        respondError('Access denied', 403);
        return;
    }

    $parser  = new LogParser();
    $entries = $parser->parseFile($real);

    $level = $_GET['level'] ?? '';
    if ($level !== '') {
        $entries = array_values(array_filter($entries, fn($e) => $e['level'] === strtoupper($level)));
    }

    echo json_encode($entries);
}

function respondError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
}
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>fast-php-log-viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        [v-cloak] { display: none; }
        .level-DEBUG     { background:#f3f4f6; color:#4b5563; }
        .level-INFO      { background:#dbeafe; color:#1d4ed8; }
        .level-NOTICE    { background:#cffafe; color:#0e7490; }
        .level-WARNING   { background:#fef9c3; color:#a16207; }
        .level-ERROR     { background:#fee2e2; color:#b91c1c; }
        .level-CRITICAL  { background:#fecaca; color:#991b1b; }
        .level-ALERT     { background:#fed7aa; color:#c2410c; }
        .level-EMERGENCY { background:#e9d5ff; color:#7e22ce; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

<div id="app" v-cloak :style="{ fontSize: fontSize + 'px' }">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-xl font-bold tracking-tight">⚡ fast-php-log-viewer</span>
        </div>
        <div class="flex items-center gap-3">
            <select v-model="selectedFile" @change="loadEntries"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— select log file —</option>
                <option v-for="f in files" :key="f.file" :value="f.file">
                    {{ f.file.split('/').pop() }} — {{ f.date }} ({{ formatSize(f.size) }})
                </option>
            </select>
            <select v-model="filterLevel" @change="applyFilters"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
            </select>
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button @click="toggleSort" :title="sortOrder === 'desc' ? 'Newest first' : 'Oldest first'"
                class="text-sm px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200 transition whitespace-nowrap">
                {{ sortOrder === 'desc' ? '↓ Newest' : '↑ Oldest' }}
            </button>
            <button @click="loadEntries" title="Refresh"
                class="text-sm px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200 transition">↺</button>
            <div class="flex items-center gap-1 border border-gray-300 rounded overflow-hidden">
                <button @click="fontSize = Math.max(10, fontSize - 1)" title="Smaller"
                    class="px-2 py-1.5 text-xs bg-white hover:bg-gray-100 transition">A−</button>
                <span class="px-2 text-xs text-gray-500 select-none">{{ fontSize }}px</span>
                <button @click="fontSize = Math.min(24, fontSize + 1)" title="Larger"
                    class="px-2 py-1.5 text-xs bg-white hover:bg-gray-100 transition">A+</button>
            </div>
        </div>
    </header>

    <!-- Stats bar -->
    <div v-if="entries.length" class="bg-white border-b border-gray-100 px-6 py-2 flex gap-4 text-xs text-gray-500">
        <span>{{ filtered.length }} / {{ entries.length }} entries</span>
        <span v-for="(count, level) in levelCounts" :key="level"
            class="px-2 py-0.5 rounded font-medium" :style="levelStyle(level)">
            {{ level }}: {{ count }}
        </span>
    </div>

    <!-- Loading / empty states -->
    <div v-if="loading" class="flex justify-center items-center py-24 text-gray-400">Loading…</div>
    <div v-else-if="!selectedFile" class="flex justify-center items-center py-24 text-gray-400">Select a log file to view entries.</div>
    <div v-else-if="!filtered.length" class="flex justify-center items-center py-24 text-gray-400">No entries match the current filters.</div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-white border-b border-gray-200 sticky top-0 z-10">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-40">Datetime</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-24">Level</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-48">Location</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Message</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-8"></th>
                </tr>
            </thead>
            <tbody>
                <template v-for="(entry, i) in filtered" :key="i">
                    <tr class="border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                        @click="toggle(i)">
                        <td class="px-4 py-2 font-mono text-xs text-gray-500 whitespace-nowrap">{{ entry.datetime }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold" :style="levelStyle(entry.level)">
                                {{ entry.level }}
                            </span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs text-gray-400 whitespace-nowrap">{{ entry.location }}</td>
                        <td class="px-4 py-2">{{ entry.message }}</td>
                        <td class="px-4 py-2 text-gray-300 text-xs">
                            <span v-if="hasContext(entry)">{{ expanded.has(i) ? '▲' : '▼' }}</span>
                        </td>
                    </tr>
                    <tr v-if="expanded.has(i) && hasContext(entry)"
                        class="bg-gray-50 border-b border-gray-100">
                        <td colspan="5" class="px-4 py-2">
                            <pre class="text-xs font-mono text-gray-600 whitespace-pre-wrap">{{ JSON.stringify(entry.context, null, 2) }}</pre>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
const { createApp, ref, computed, reactive, watch } = Vue;

const LEVEL_STYLES = {
    DEBUG:     'background:#f3f4f6;color:#4b5563',
    INFO:      'background:#dbeafe;color:#1d4ed8',
    NOTICE:    'background:#cffafe;color:#0e7490',
    WARNING:   'background:#fef9c3;color:#a16207',
    ERROR:     'background:#fee2e2;color:#b91c1c',
    CRITICAL:  'background:#fecaca;color:#991b1b',
    ALERT:     'background:#fed7aa;color:#c2410c',
    EMERGENCY: 'background:#e9d5ff;color:#7e22ce',
};

createApp({
    setup() {
        const files       = ref([]);
        const entries     = ref([]);
        const filtered    = ref([]);
        const selectedFile = ref('');
        const filterLevel = ref('');
        const filterText  = ref('');
        const loading     = ref(false);
        const expanded    = ref(new Set());
        const sortOrder   = ref('desc'); // desc = newest first

        const levels = Object.keys(LEVEL_STYLES);
        const fontSize = ref(parseInt(localStorage.getItem('fplv_fontsize') || '14'));
        watch(fontSize, v => localStorage.setItem('fplv_fontsize', String(v)));

        const levelCounts = computed(() => {
            const counts = {};
            for (const e of entries.value) {
                counts[e.level] = (counts[e.level] ?? 0) + 1;
            }
            return counts;
        });

        const levelStyle = (level) => LEVEL_STYLES[level] ?? '';
        const hasContext = (entry) => entry.context && Object.keys(entry.context).length > 0;

        async function fetchJson(url) {
            const r = await fetch(url);
            if (!r.ok) throw new Error(await r.text());
            return r.json();
        }

        async function loadFiles() {
            files.value = await fetchJson('?action=files');
            if (files.value.length) {
                selectedFile.value = files.value[0].file;
                await loadEntries();
            }
        }

        async function loadEntries() {
            if (!selectedFile.value) return;
            loading.value = true;
            expanded.value = new Set();
            try {
                entries.value = await fetchJson('?action=entries&file=' + encodeURIComponent(selectedFile.value));
                applyFilters();
            } finally {
                loading.value = false;
            }
        }

        function applyFilters() {
            let result = entries.value;
            if (filterLevel.value) {
                result = result.filter(e => e.level === filterLevel.value);
            }
            if (filterText.value.trim()) {
                const q = filterText.value.toLowerCase();
                result = result.filter(e =>
                    e.message.toLowerCase().includes(q) ||
                    e.location.toLowerCase().includes(q)
                );
            }
            if (sortOrder.value === 'asc') {
                result = [...result].reverse();
            }
            filtered.value = result;
        }

        function toggle(i) {
            const s = new Set(expanded.value);
            s.has(i) ? s.delete(i) : s.add(i);
            expanded.value = s;
        }

        function toggleSort() {
            sortOrder.value = sortOrder.value === 'desc' ? 'asc' : 'desc';
            applyFilters();
        }

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        loadFiles();

        return { files, entries, filtered, selectedFile, filterLevel, filterText,
                 loading, expanded, levels, levelCounts,
                 loadEntries, applyFilters, toggle, formatSize, levelStyle, hasContext, fontSize,
                 sortOrder, toggleSort };
    }
}).mount('#app');
</script>
</body>
</html>
