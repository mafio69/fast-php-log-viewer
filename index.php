<?php
if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}
if (!defined('EDITOR_URL')) {
    define('EDITOR_URL', getenv('EDITOR_URL') ?: 'phpstorm://open?file={file}&line={line}');
}
if (isset($_GET['action'])) {
    require_once __DIR__ . '/src/api.php';
    exit;
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>fast-php-log-viewer</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    <style>
        [v-cloak] { display: none; }
        body { background:#111; color:#e5e7eb; }
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:#1a1a1a; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:3px; }
    </style>
</head>
<body class="h-screen overflow-hidden" style="background:#111;color:#e5e7eb;">

<div id="app" v-cloak class="flex h-screen" :style="{ fontSize: fontSize + 'px' }">

    <!-- Sidebar -->
    <aside style="width:200px;min-width:200px;background:#1a1a1a;border-right:1px solid #2a2a2a;" class="flex flex-col">
        <div class="px-3 py-3" style="border-bottom:1px solid #2a2a2a;">
            <div class="font-bold text-sm" style="color:#e5e7eb;">⚡ log-viewer</div>
        </div>

        <!-- File list -->
        <div class="flex-1 overflow-y-auto">
            <div v-for="f in files" :key="f.file"
                @click="selectFile(f.file)"
                class="px-3 py-2 cursor-pointer"
                style="border-bottom:1px solid #222;"
                :style="selectedFile === f.file
                    ? 'background:#1e3a5f;border-left:3px solid #3b82f6;color:#93c5fd;'
                    : 'color:#9ca3af;border-left:3px solid transparent;'">
                <div class="font-medium truncate">{{ f.file.split('/').pop() }}</div>
                <div style="color:#6b7280;font-size:0.85em;">{{ f.date }} · {{ formatSize(f.size) }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="border-top:1px solid #2a2a2a;" class="px-3 py-3 flex flex-col gap-3">

            <!-- Date range -->
            <div>
                <div class="text-xs font-semibold mb-1" style="color:#6b7280;letter-spacing:.05em;">ZAKRES DAT</div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-1 text-xs" style="color:#9ca3af;">
                        <span style="width:20px;">Od</span>
                        <input type="date" v-model="dateFrom" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs"
                            style="background:#222;border:1px solid #333;color:#e5e7eb;">
                    </div>
                    <div class="flex items-center gap-1 text-xs" style="color:#9ca3af;">
                        <span style="width:20px;">Do</span>
                        <input type="date" v-model="dateTo" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs"
                            style="background:#222;border:1px solid #333;color:#e5e7eb;">
                    </div>
                </div>
                <button @click="applyFilters" class="mt-1 w-full rounded py-0.5 text-xs font-medium"
                    style="background:#1d4ed8;color:#fff;">Zastosuj</button>
            </div>

            <!-- Levels -->
            <div>
                <div class="text-xs font-semibold mb-1" style="color:#6b7280;letter-spacing:.05em;">POZIOMY</div>
                <div class="flex flex-col gap-0.5">
                    <label v-for="level in levels" :key="level" class="flex items-center gap-2 text-xs cursor-pointer" style="color:#9ca3af;">
                        <span class="w-2 h-2 rounded-full inline-block" :style="'background:' + levelDot(level)"></span>
                        <input type="checkbox" :checked="!excludedLevels.includes(level)" @change="toggleLevel(level)" class="hidden">
                        <span @click="toggleLevel(level)"
                            :style="excludedLevels.includes(level) ? 'color:#4b5563;' : 'color:#e5e7eb;'">
                            {{ level }}
                        </span>
                        <span class="ml-auto" style="color:#6b7280;">{{ levelCounts[level] || '' }}</span>
                    </label>
                </div>
            </div>

            <!-- Time range -->
            <div>
                <div class="text-xs font-semibold mb-1" style="color:#6b7280;letter-spacing:.05em;">GODZINY</div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-1 text-xs" style="color:#9ca3af;">
                        <span style="width:20px;">Od</span>
                        <input type="time" v-model="timeFrom" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs"
                            style="background:#222;border:1px solid #333;color:#e5e7eb;">
                    </div>
                    <div class="flex items-center gap-1 text-xs" style="color:#9ca3af;">
                        <span style="width:20px;">Do</span>
                        <input type="time" v-model="timeTo" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs"
                            style="background:#222;border:1px solid #333;color:#e5e7eb;">
                    </div>
                </div>
            </div>

            <!-- Sort -->
            <div>
                <div class="text-xs font-semibold mb-1" style="color:#6b7280;letter-spacing:.05em;">SORTOWANIE</div>
                <button @click="toggleSort"
                    class="w-full rounded px-2 py-1 text-xs text-left"
                    style="background:#222;border:1px solid #333;color:#9ca3af;">
                    {{ sortOrder === 'desc' ? '↓ Newest first' : '↑ Oldest first' }}
                </button>
            </div>

            <!-- Stats -->
            <div class="text-xs" style="color:#6b7280;">
                {{ filtered.length }} entries<br>
                <span v-if="selectedFile">{{ selectedFile.split('/').pop() }}</span>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center gap-2 px-4 py-2" style="background:#1a1a1a;border-bottom:1px solid #2a2a2a;">
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="rounded px-3 py-1 text-sm flex-1 max-w-xs"
                style="background:#222;border:1px solid #333;color:#e5e7eb;">
            <button @click="loadEntries" title="Refresh"
                class="px-3 py-1 rounded text-sm"
                style="background:#222;border:1px solid #333;color:#9ca3af;">↺</button>
            <div class="flex items-center gap-1 rounded overflow-hidden" style="border:1px solid #333;">
                <button @click="fontSize = Math.max(10, fontSize - 1)"
                    class="px-2 py-1 text-xs" style="background:#222;color:#9ca3af;">A−</button>
                <span class="px-2 text-xs" style="color:#6b7280;">{{ fontSize }}px</span>
                <button @click="fontSize = Math.min(24, fontSize + 1)"
                    class="px-2 py-1 text-xs" style="background:#222;color:#9ca3af;">A+</button>
            </div>
        </div>

        <!-- States -->
        <div v-if="loading" class="flex-1 flex items-center justify-center" style="color:#6b7280;">Loading…</div>
        <div v-else-if="!selectedFile" class="flex-1 flex items-center justify-center" style="color:#6b7280;">Select a log file.</div>
        <div v-else-if="!filtered.length" class="flex-1 flex items-center justify-center" style="color:#6b7280;">No entries match filters.</div>

        <!-- Table -->
        <div v-else class="flex-1 overflow-auto">
            <table class="w-full text-sm border-collapse">
                <thead style="background:#1a1a1a;border-bottom:1px solid #2a2a2a;" class="sticky top-0 z-10">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium text-xs" style="color:#6b7280;width:155px;">Datetime</th>
                        <th class="text-left px-3 py-2 font-medium text-xs" style="color:#6b7280;width:90px;">Level</th>
                        <th class="text-left px-3 py-2 font-medium text-xs" style="color:#6b7280;width:200px;">Location</th>
                        <th class="text-left px-3 py-2 font-medium text-xs" style="color:#6b7280;">Message</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(entry, i) in filtered" :key="i">
                        <tr @click="toggle(i)" class="cursor-pointer"
                            :style="'border-bottom:1px solid #1f1f1f;' + rowBg(entry.level)">
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap" style="color:#6b7280;">{{ entry.datetime }}</td>
                            <td class="px-3 py-1.5 text-xs font-bold whitespace-nowrap" :style="'color:' + levelColor(entry.level)">{{ entry.level }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap" style="color:#6b7280;">
                                <a v-if="editorUrl && entry.location"
                                   :href="openInEditor(entry.location)"
                                   @click.stop
                                   class="hover:underline" :style="'color:' + levelColor(entry.level)">{{ entry.location }}</a>
                                <span v-else>{{ entry.location }}</span>
                            </td>
                            <td class="px-3 py-1.5 truncate max-w-0 w-full" style="color:#d1d5db;">
                                <span class="block truncate">{{ entry.message }}</span>
                                <span v-if="hasContext(entry)" class="text-xs" style="color:#4b5563;">{{ expanded[i] ? '▲' : '▼' }}</span>
                            </td>
                        </tr>
                        <tr v-if="expanded[i] && hasContext(entry)" style="background:#161616;border-bottom:1px solid #1f1f1f;">
                            <td colspan="4" class="px-3 py-2">
                                <pre class="text-xs font-mono whitespace-pre-wrap" style="color:#9ca3af;">{{ JSON.stringify(entry.context, null, 2) }}</pre>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const { createApp, ref, computed, reactive, watch } = Vue;
const EDITOR_URL = <?= json_encode(EDITOR_URL) ?>;

const LEVEL_COLORS = {
    DEBUG:'#60a5fa', INFO:'#34d399', NOTICE:'#22d3ee',
    WARNING:'#fbbf24', ERROR:'#f87171', CRITICAL:'#ef4444',
    ALERT:'#fb923c', EMERGENCY:'#c084fc',
};
const LEVEL_DOTS = {
    DEBUG:'#3b82f6', INFO:'#10b981', NOTICE:'#06b6d4',
    WARNING:'#f59e0b', ERROR:'#ef4444', CRITICAL:'#dc2626',
    ALERT:'#f97316', EMERGENCY:'#a855f7',
};
const ROW_BG = {
    ERROR:'background:#1f1010;', CRITICAL:'background:#1f0a0a;',
    ALERT:'background:#1f1208;', EMERGENCY:'background:#160d1f;',
    WARNING:'background:#1a1600;',
};

createApp({
    setup() {
        const files        = ref([]);
        const entries      = ref([]);
        const filtered     = ref([]);
        const selectedFile = ref('');
        const filterText   = ref('');
        const loading      = ref(false);
        const expanded     = reactive({});
        const excludedLevels = ref([]);
        const sortOrder    = ref('desc');
        const dateFrom     = ref('');
        const dateTo       = ref('');
        const timeFrom     = ref('');
        const timeTo       = ref('');
        const editorUrl    = ref(EDITOR_URL);
        const fontSize     = ref(parseInt(localStorage.getItem('fplv_fontsize') || '13'));
        const levels       = Object.keys(LEVEL_COLORS);

        watch(fontSize, v => localStorage.setItem('fplv_fontsize', String(v)));

        const levelCounts = computed(() => {
            const c = {};
            for (const e of entries.value) c[e.level] = (c[e.level] ?? 0) + 1;
            return c;
        });

        const levelColor  = l => LEVEL_COLORS[l] ?? '#9ca3af';
        const levelDot    = l => LEVEL_DOTS[l]   ?? '#6b7280';
        const rowBg       = l => ROW_BG[l]        ?? '';
        const hasContext  = e => e.context && Object.keys(e.context).length > 0;

        function openInEditor(location) {
            const [file, line] = location.split(':');
            return editorUrl.value.replace('{file}', encodeURIComponent(file)).replace('{line}', line ?? '1');
        }

        function formatSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
            return (b/1048576).toFixed(1) + ' MB';
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

        async function selectFile(path) {
            selectedFile.value = path;
            await loadEntries();
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
            let r = entries.value;
            if (excludedLevels.value.length)
                r = r.filter(e => !excludedLevels.value.includes(e.level));
            if (filterText.value.trim()) {
                const q = filterText.value.toLowerCase();
                r = r.filter(e => e.message.toLowerCase().includes(q) || e.location.toLowerCase().includes(q));
            }
            if (dateFrom.value || dateTo.value) {
                r = r.filter(e => {
                    const d = e.datetime.slice(0, 10);
                    if (dateFrom.value && d < dateFrom.value) return false;
                    if (dateTo.value   && d > dateTo.value)   return false;
                    return true;
                });
            }
            if (timeFrom.value || timeTo.value) {
                r = r.filter(e => {
                    const t = e.datetime.slice(11, 16);
                    if (timeFrom.value && t < timeFrom.value) return false;
                    if (timeTo.value   && t > timeTo.value)   return false;
                    return true;
                });
            }
            if (sortOrder.value === 'asc') r = [...r].reverse();
            filtered.value = r;
        }

        function toggleLevel(level) {
            const arr = excludedLevels.value;
            const idx = arr.indexOf(level);
            if (idx >= 0) arr.splice(idx, 1);
            else arr.push(level);
            applyFilters();
        }

        function toggle(i) { expanded[i] ? delete expanded[i] : (expanded[i] = true); }

        function toggleSort() {
            sortOrder.value = sortOrder.value === 'desc' ? 'asc' : 'desc';
            applyFilters();
        }

        loadFiles();

        return {
            files, entries, filtered, selectedFile, filterText, loading, expanded,
            levels, levelCounts, dateFrom, dateTo, timeFrom, timeTo, sortOrder, fontSize,
            excludedLevels, editorUrl,
            selectFile, loadEntries, applyFilters, toggle, toggleSort, toggleLevel,
            formatSize, levelColor, levelDot, rowBg, hasContext, openInEditor,
        };
    }
}).mount('#app');
</script>
</body>
</html>
