<?php
/**
 * fast-php-log-viewer
 * Drop this file next to your logs/ directory (or configure LOG_DIR).
 */
if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}

// Serve API requests
if (isset($_GET['action'])) {
    require_once __DIR__ . '/src/api.php';
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
                    {{ f.date }} ({{ formatSize(f.size) }})
                </option>
            </select>
            <select v-model="filterLevel" @change="applyFilters"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
            </select>
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="text-sm border border-gray-300 rounded px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
        const expanded    = reactive(new Set());

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
            expanded.clear();
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
            filtered.value = result;
        }

        function toggle(i) {
            expanded.has(i) ? expanded.delete(i) : expanded.add(i);
        }

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
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
