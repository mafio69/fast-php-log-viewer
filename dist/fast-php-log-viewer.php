<?php
/**
 * fast-php-log-viewer — single-file drop-in
 * https://github.com/mafio69/fast-php-log-viewer
 *
 * Usage (no Composer needed):
 *   1. Copy this file anywhere in your project
 *   2. Open in browser — it auto-detects ./logs next to this file
 *   3. Or set LOG_DIR: define('LOG_DIR', '/path/to/logs') before including
 *
 * @version 1.0.0
 */

declare(strict_types=1);

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}

// ─── LogParser ───────────────────────────────────────────────────────────────

namespace Mariusz\LogViewer;

class LogParser
{
    private const PATTERN_FPL    = '/^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$/';
    private const PATTERN_LEGACY = '/^(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --- (?P<level>[A-Z]+): (?P<rest>.*)$/';

    public function parseFile(string $path): array
    {
        if (!is_readable($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) return [];

        $entries = [];
        $i = 0; $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            if (preg_match(self::PATTERN_FPL, $line, $m)) {
                $entries[] = [
                    'datetime' => $m['datetime'],
                    'level'    => strtoupper($m['level']),
                    'location' => $m['location'],
                    'message'  => $m['message'],
                    'context'  => isset($m['context']) && $m['context'] !== '' ? (json_decode($m['context'], true) ?? []) : [],
                ];
                $i++; continue;
            }

            if (preg_match(self::PATTERN_LEGACY, $line, $m)) {
                $rest = $m['rest'];
                $j = $i + 1;
                while ($j < $count && !preg_match(self::PATTERN_FPL, $lines[$j]) && !preg_match(self::PATTERN_LEGACY, $lines[$j])) {
                    $rest .= "\n" . $lines[$j]; $j++;
                }
                $context = []; $message = trim($rest); $location = '';
                $decoded = json_decode(trim($rest), true);
                if (is_array($decoded)) {
                    $context = $decoded;
                    if (isset($decoded['info']) && preg_match('/::([^:]+)::(\d+)$/', $decoded['info'], $im)) {
                        $location = $im[1] . ':' . $im[2];
                    }
                    $message = $decoded['info'] ?? $m['level'];
                }
                $entries[] = ['datetime' => $m['datetime'], 'level' => strtoupper($m['level']), 'location' => $location, 'message' => $message, 'context' => $context];
                $i = $j; continue;
            }

            $i++;
        }

        return array_reverse($entries);
    }

    public function parseLine(string $line): ?array
    {
        if (!preg_match(self::PATTERN_FPL, $line, $m)) return null;
        return [
            'datetime' => $m['datetime'],
            'level'    => strtoupper($m['level']),
            'location' => $m['location'],
            'message'  => $m['message'],
            'context'  => isset($m['context']) && $m['context'] !== '' ? (json_decode($m['context'], true) ?? []) : [],
        ];
    }
}

// ─── LogFinder ───────────────────────────────────────────────────────────────

class LogFinder
{
    public function __construct(private readonly string $logDir) {}

    public function findAll(): array
    {
        $dir   = self::normalizePath($this->logDir);
        $files = array_unique(array_merge(
            glob($dir . '/*/*/*.log') ?: [],
            glob($dir . '/*/*.log')   ?: [],
            glob($dir . '/*.log')     ?: [],
        ));

        $result = [];
        foreach ($files as $path) {
            $result[] = ['path' => self::normalizePath($path), 'date' => $this->extractDate($path), 'size' => filesize($path) ?: 0];
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
        $name = basename($path, '.log');
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $name, $m)) return $m[1];
        return date('Y-m-d', filemtime($path) ?: time());
    }
}

// ─── API ─────────────────────────────────────────────────────────────────────

namespace {

use Mariusz\LogViewer\LogFinder;
use Mariusz\LogViewer\LogParser;

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    try {
        match ($_GET['action']) {
            'files'   => (function () {
                $finder = new LogFinder(LOG_DIR);
                echo json_encode(array_map(fn($f) => [
                    'file' => $f['path'],
                    'date' => $f['date'],
                    'size' => $f['size'],
                ], $finder->findAll()));
            })(),
            'entries' => (function () {
                $file = $_GET['file'] ?? '';
                if ($file === '') { http_response_code(400); echo json_encode(['error' => 'Missing file']); return; }
                $real    = realpath($file);
                $logReal = realpath(LOG_DIR);
                $real    = str_replace('\\', '/', $real    !== false ? $real    : $file);
                $logReal = str_replace('\\', '/', $logReal !== false ? $logReal : LOG_DIR);
                if (!str_starts_with($real, rtrim($logReal, '/') . '/')) {
                    http_response_code(403); echo json_encode(['error' => 'Access denied']); return;
                }
                $entries = (new LogParser())->parseFile($real);
                $level   = $_GET['level'] ?? '';
                if ($level !== '') {
                    $entries = array_values(array_filter($entries, fn($e) => $e['level'] === strtoupper($level)));
                }
                echo json_encode($entries);
            })(),
            default => (function () { http_response_code(400); echo json_encode(['error' => 'Unknown action']); })(),
        };
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

} // end namespace
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
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-wrap gap-3">
        <span class="text-xl font-bold tracking-tight">⚡ fast-php-log-viewer</span>
        <div class="flex items-center gap-2 flex-wrap">
            <select v-model="selectedFile" @change="loadEntries"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— select log file —</option>
                <option v-for="f in files" :key="f.file" :value="f.file">
                    {{ f.date }} ({{ formatSize(f.size) }})
                </option>
            </select>
            <select v-model="filterLevel" @change="applyFilters"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
            </select>
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 w-44 focus:outline-none focus:ring-2 focus:ring-blue-500">
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

    <div v-if="entries.length" class="bg-white border-b border-gray-100 px-6 py-2 flex gap-3 flex-wrap text-xs text-gray-500">
        <span>{{ filtered.length }} / {{ entries.length }} entries</span>
        <span v-for="(count, level) in levelCounts" :key="level"
            class="px-2 py-0.5 rounded font-semibold" :style="levelStyle(level)">
            {{ level }}: {{ count }}
        </span>
    </div>

    <div v-if="loading" class="flex justify-center py-24 text-gray-400">Loading…</div>
    <div v-else-if="!selectedFile" class="flex justify-center py-24 text-gray-400">Select a log file to view entries.</div>
    <div v-else-if="!filtered.length" class="flex justify-center py-24 text-gray-400">No entries match the current filters.</div>

    <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-white border-b border-gray-200 sticky top-0 z-10">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-40">Datetime</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-24">Level</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500 w-48">Location</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Message</th>
                    <th class="w-6"></th>
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
                        <td class="px-4 py-2 text-gray-300 text-xs text-center">
                            <span v-if="hasContext(entry)">{{ expanded.has(i) ? '▲' : '▼' }}</span>
                        </td>
                    </tr>
                    <tr v-if="expanded.has(i) && hasContext(entry)" class="bg-gray-50 border-b border-gray-100">
                        <td colspan="5" class="px-6 py-3">
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
        const files        = ref([]);
        const entries      = ref([]);
        const filtered     = ref([]);
        const selectedFile = ref('');
        const filterLevel  = ref('');
        const filterText   = ref('');
        const loading      = ref(false);
        const expanded     = reactive(new Set());
        const levels       = Object.keys(LEVEL_STYLES);
        const fontSize     = ref(parseInt(localStorage.getItem('fplv_fontsize') || '14'));
        watch(fontSize, v => localStorage.setItem('fplv_fontsize', String(v)));

        const levelCounts = computed(() => {
            const c = {};
            for (const e of entries.value) c[e.level] = (c[e.level] ?? 0) + 1;
            return c;
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
            expanded.clear();
            try {
                entries.value = await fetchJson('?action=entries&file=' + encodeURIComponent(selectedFile.value));
                applyFilters();
            } finally {
                loading.value = false;
            }
        }

        function applyFilters() {
            let r = entries.value;
            if (filterLevel.value) r = r.filter(e => e.level === filterLevel.value);
            if (filterText.value.trim()) {
                const q = filterText.value.toLowerCase();
                r = r.filter(e => e.message.toLowerCase().includes(q) || e.location.toLowerCase().includes(q));
            }
            filtered.value = r;
        }

        function toggle(i) { expanded.has(i) ? expanded.delete(i) : expanded.add(i); }

        function formatSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            return (b / 1048576).toFixed(1) + ' MB';
        }

        loadFiles();

        return { files, entries, filtered, selectedFile, filterLevel, filterText,
                 loading, expanded, levels, levelCounts,
                 loadEntries, applyFilters, toggle, formatSize, levelStyle, hasContext, fontSize };
    }
}).mount('#app');
</script>
</body>
</html>
