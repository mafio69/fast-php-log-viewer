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
                    {{ f.file.split('/').pop() }} — {{ f.date }} ({{ formatSize(f.size) }})
                </option>
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
    <div v-if="selectedFile" class="bg-white border-b border-gray-100 px-6 py-2 flex items-center gap-2 text-xs text-gray-500">
        <span class="shrink-0 mr-1">{{ filtered.length }} / {{ entries.length }}</span>
        <button v-for="level in levels" :key="level"
            @click="toggleLevel(level)"
            class="px-2 py-0.5 rounded font-medium cursor-pointer select-none transition-all"
            :style="excludedLevels.has(level) ? levelStyleFaded(level) : levelStyle(level)"
            :title="(excludedLevels.has(level) ? 'Show ' : 'Hide ') + level">
            {{ level }}<span v-if="levelCounts[level]"> {{ levelCounts[level] }}</span>
        </button>
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
                    <tr class="border-b border-gray-100 hover:brightness-95 cursor-pointer"
                        :style="rowStyle(entry.level)"
                        @click="toggle(i)"
                        :title="entryTooltip(entry)">
                        <td class="px-4 py-1.5 font-mono text-xs text-gray-500 whitespace-nowrap">{{ entry.datetime }}</td>
                        <td class="px-4 py-1.5">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold" :style="levelStyle(entry.level)">
                                {{ entry.level }}
                            </span>
                        </td>
                        <td class="px-4 py-1.5 font-mono text-xs whitespace-nowrap" :style="levelStyle(entry.level)">
                            <div>{{ entry.location }}</div>
                            <div class="opacity-50">{{ entrySize(entry) }}</div>
                        </td>
                        <td class="px-4 py-1.5 truncate max-w-0 w-full">
                            <span class="block truncate">{{ entry.message }}</span>
                        </td>
                        <td class="px-4 py-1.5 text-gray-300 text-xs whitespace-nowrap">
                            <span v-if="hasContext(entry)">{{ expanded[i] ? '▲' : '▼' }}</span>
                        </td>
                    </tr>
                    <tr v-if="expanded[i] && hasContext(entry)"
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
    DEBUG:     'background:#dbeafe;color:#1d4ed8',
    INFO:      'background:#f3f4f6;color:#4b5563',
    NOTICE:    'background:#cffafe;color:#0e7490',
    WARNING:   'background:#fef9c3;color:#a16207',
    ERROR:     'background:#fee2e2;color:#b91c1c',
    CRITICAL:  'background:#fecaca;color:#991b1b',
    ALERT:     'background:#fed7aa;color:#c2410c',
    EMERGENCY: 'background:#e9d5ff;color:#7e22ce',
};
const LEVEL_STYLES_FADED = {
    DEBUG:     'background:#eff6ff;color:#93c5fd',
    INFO:      'background:#f9fafb;color:#9ca3af',
    NOTICE:    'background:#ecfeff;color:#67e8f9',
    WARNING:   'background:#fefce8;color:#fde047',
    ERROR:     'background:#fff1f2;color:#fca5a5',
    CRITICAL:  'background:#fff5f5;color:#fca5a5',
    ALERT:     'background:#fff7ed;color:#fdba74',
    EMERGENCY: 'background:#faf5ff;color:#d8b4fe',
};
const ROW_STYLES = {
    DEBUG:     'background:#eff6ff',
    INFO:      '',
    NOTICE:    'background:#f0fdfe',
    WARNING:   'background:#fefce8',
    ERROR:     'background:#fff1f2',
    CRITICAL:  'background:#fff5f5',
    ALERT:     'background:#fff7ed',
    EMERGENCY: 'background:#faf5ff',
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
        const expanded    = reactive({});
        const excludedLevels = ref(new Set());
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
        const levelStyleFaded = (level) => LEVEL_STYLES_FADED[level] ?? '';
        const rowStyle = (level) => ROW_STYLES[level] ?? '';
        const hasContext = (entry) => entry.context && Object.keys(entry.context).length > 0;

        function entryTooltip(entry) {
            const text = entry.message + (hasContext(entry) ? ' ' + JSON.stringify(entry.context) : '');
            const words = text.trim().split(/\s+/).length;
            const kb = (new TextEncoder().encode(text).length / 1024).toFixed(2);
            return `${words} słów · ${kb} KB`;
        }

        function entrySize(entry) {
            const bytes = new TextEncoder().encode(entry.message + JSON.stringify(entry.context ?? {})).length;
            return bytes < 1024 ? bytes + ' B' : (bytes / 1024).toFixed(1) + ' KB';
        }

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
            Object.keys(expanded).forEach(k => delete expanded[k]);
            try {
                entries.value = await fetchJson('?action=entries&file=' + encodeURIComponent(selectedFile.value));
                applyFilters();
            } finally {
                loading.value = false;
            }
        }

        function applyFilters() {
            let result = entries.value;
            if (excludedLevels.value.size) {
                result = result.filter(e => !excludedLevels.value.has(e.level));
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

        function toggleLevel(level) {
            const s = new Set(excludedLevels.value);
            s.has(level) ? s.delete(level) : s.add(level);
            excludedLevels.value = s;
            applyFilters();
        }

        function toggle(i) {
            expanded[i] ? delete expanded[i] : expanded[i] = true;
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

        return { files, entries, filtered, selectedFile, filterText,
                 loading, expanded, levels, levelCounts,
                 loadEntries, applyFilters, toggle, formatSize, levelStyle, levelStyleFaded, rowStyle, hasContext, entryTooltip, entrySize, fontSize,
                 sortOrder, toggleSort, excludedLevels, toggleLevel };
    }
}).mount('#app');
</script>
</body>
</html>
