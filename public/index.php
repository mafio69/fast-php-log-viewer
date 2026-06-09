<?php
if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}
if (!defined('EDITOR_URL')) {
    define('EDITOR_URL', getenv('EDITOR_URL') ?: 'phpstorm://open?file={file}&line={line}');
}
if (isset($_GET['action'])) {
    require_once __DIR__ . '/../src/Controller/LogController.php';
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
        [v-cloak] { display: none; }
        body { background:#000; color:#00ff00; font-family: 'Courier New', monospace; }
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:#001100; }
        ::-webkit-scrollbar-thumb { background:#003300; border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:#004400; }
        .crt-glow { text-shadow: 0 0 5px #00ff00, 0 0 10px #00ff00; }
        .crt-border { border: 1px solid #00ff00; }
        .crt-bg { background: #001100; }
        .crt-text { color: #00ff00; }
        .crt-dim { color: #006600; }
        .crt-input { background: #000; border: 1px solid #00ff00; color: #00ff00; }
        .crt-input:focus { outline: none; box-shadow: 0 0 5px #00ff00; }
        .crt-button { background: #001100; border: 1px solid #00ff00; color: #00ff00; cursor: pointer; }
        .crt-button:hover { background: #002200; box-shadow: 0 0 5px #00ff00; }

        /* DataTables CRT theme */
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #00ff00 !important;
            font-family: 'Courier New', monospace !important;
        }
        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input {
            background: #000 !important;
            border: 1px solid #00ff00 !important;
            color: #00ff00 !important;
            font-family: 'Courier New', monospace !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #00ff00 !important;
            border: 1px solid #00ff00 !important;
            background: #000 !important;
            font-family: 'Courier New', monospace !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #002200 !important;
            color: #00ff00 !important;
            border: 1px solid #00ff00 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: #006600 !important;
            border: 1px solid #003300 !important;
        }
        table.dataTable thead th, table.dataTable thead td {
            border-bottom: 1px solid #00ff00 !important;
            color: #00ff00 !important;
            font-family: 'Courier New', monospace !important;
            background: #001100 !important;
        }
        table.dataTable tbody tr {
            background: #000 !important;
        }
        table.dataTable tbody tr:hover {
            background: #001100 !important;
        }
        table.dataTable td {
            color: #00ff00 !important;
            font-family: 'Courier New', monospace !important;
        }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing {
            margin: 12px !important;
        }
    </style>
</head>
<body class="h-screen overflow-hidden crt-text crt-bg">

<div id="app" v-cloak class="flex h-screen" :style="{ fontSize: fontSize + 'px' }">

    <!-- Sidebar -->
    <aside style="width:280px;min-width:280px;background:#000;border-right:1px solid #00ff00;" class="flex flex-col">
        <div class="px-3 py-3 crt-border" style="border-bottom:1px solid #00ff00;">
            <div class="font-bold text-sm crt-glow">⚡ LOG-VIEWER</div>
        </div>

        <!-- Filters -->
        <div style="border-top:1px solid #00ff00;" class="px-3 py-2 flex flex-col gap-2">

            <!-- Sort -->
            <div>
                <div class="text-xs font-semibold mb-1 crt-dim">SORTOWANIE</div>
                <button @click="toggleSort"
                    class="w-full rounded px-2 py-1 text-xs text-left crt-button">
                    {{ sortOrder === 'desc' ? '↓ Najnowsze' : '↑ Najstarsze' }}
                </button>
            </div>

            <!-- Date range -->
            <div>
                <div class="text-xs font-semibold mb-1 crt-dim">ZAKRES DAT</div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-1 text-xs crt-dim">
                        <span style="width:20px;">Od</span>
                        <input type="date" v-model="dateFrom" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs crt-input">
                    </div>
                    <div class="flex items-center gap-1 text-xs crt-dim">
                        <span style="width:20px;">Do</span>
                        <input type="date" v-model="dateTo" @change="applyFilters"
                            class="flex-1 rounded px-1 py-0.5 text-xs crt-input">
                    </div>
                </div>
                <button @click="applyFilters" class="mt-1 w-full rounded py-0.5 text-xs font-medium crt-button">Zastosuj</button>
            </div>

            <!-- Levels -->
            <div>
                <div class="text-xs font-semibold mb-1 crt-dim">POZIOMY</div>
                <div class="flex flex-col gap-0.5">
                    <label v-for="level in levels" :key="level" class="flex items-center gap-2 text-xs cursor-pointer crt-dim">
                        <span class="w-2 h-2 rounded-full inline-block" :style="'background:' + levelDot(level)"></span>
                        <input type="checkbox" :checked="!excludedLevels.includes(level)" @change="toggleLevel(level)" class="hidden">
                        <span @click="toggleLevel(level)"
                            :style="excludedLevels.includes(level) ? 'color:#003300;' : 'color:#00ff00;'">
                            {{ level }}
                        </span>
                        <span class="ml-auto crt-dim">{{ levelCounts[level] || '' }}</span>
                    </label>
                </div>
            </div>

            <!-- Stats -->
            <div class="text-xs crt-dim">
                {{ filtered.length }} entries<br>
                <span v-if="selectedFile">{{ selectedFile.split('/').pop() }}</span>
            </div>
        </div>

        <!-- Directory selector -->
        <div v-if="directories.length > 1" class="px-3 py-2" style="border-bottom:1px solid #00ff00;">
            <div class="text-xs font-semibold mb-1 crt-dim">KATALOG</div>
            <select v-model="selectedDir" @change="changeDir"
                class="w-full rounded px-2 py-1 text-xs crt-input">
                <option v-for="d in directories" :key="d.key" :value="d.key">{{ d.key }}</option>
            </select>
        </div>

        <!-- File list -->
        <div class="flex-1 overflow-y-auto" style="flex:3;">
            <div v-for="f in files" :key="f.file"
                @click="selectFile(f.file)"
                class="px-3 py-2 cursor-pointer"
                style="border-bottom:1px solid #002200;"
                :style="selectedFile === f.file
                    ? 'background:#002200;border-left:3px solid #00ff00;color:#00ff00;'
                    : 'color:#006600;border-left:3px solid transparent;'">
                <div class="font-medium truncate">
                    <span v-if="f.ssh" style="color:#00ccff;">🔗 </span>{{ f.ssh ? f.sshRealPath.split('/').pop() : f.file.split('/').pop() }}
                </div>
                <div class="crt-dim text-xs">
                    <span v-if="f.ssh" style="color:#00ccff;">{{ f.sshConn.name }} · </span>{{ formatDate(f.date) }} · {{ formatSize(f.size) }}
                </div>
                <div v-if="f.allow" class="crt-dim text-xs">allow: {{ f.allow }}</div>
            </div>
        </div>

        <!-- Direct file path -->
        <div class="px-3 py-3" style="border-bottom:1px solid #00ff00;background:#001100;">
            <div class="text-xs font-bold mb-2 crt-text">📂 ŚCIEŻKA DO PLIKU</div>
            <input type="text" v-model="directFilePath" placeholder="/var/log/syslog lub c:\logs\php_error.log"
                class="w-full rounded px-2 py-1 text-xs crt-input mb-1"
                @keyup.enter="loadDirectFile">
            <div v-if="fileCheckResult" class="text-xs mb-1" :style="fileCheckResult.exists ? 'color:#00ff00;' : 'color:#ff0000;'">
                {{ fileCheckResult.exists ? '✓ Plik znaleziony (' + formatSize(fileCheckResult.size) + ')' : '✗ Plik nie istnieje' }}
            </div>
            <div class="flex gap-1">
                <button @click="checkDirectFile" class="flex-1 rounded px-2 py-1 text-xs crt-button">
                    🔍 SPRAWDŹ
                </button>
                <button @click="loadDirectFile" class="flex-1 rounded px-2 py-1 text-xs crt-button font-bold">
                    ⚡ ZAŁADUJ
                </button>
            </div>
        </div>

        <!-- Add allowed directory -->
        <div class="px-3 py-2" style="border-bottom:1px solid #00ff00;">
            <div class="text-xs font-semibold mb-1 crt-dim">➕ DODAJ KATALOG DOZWOLONY</div>
            <input type="text" v-model="allowedDirPath" placeholder="/var/log"
                class="w-full rounded px-2 py-1 text-xs crt-input mb-2">
            <button @click="addAllowedDir" class="w-full rounded px-2 py-1 text-xs crt-button mb-2">
                DODAJ
            </button>
            <button @click="cleanupDuplicates" class="w-full rounded px-2 py-1 text-xs crt-button crt-dim">
                🧹 Czyść duplikaty
            </button>
        </div>

        <!-- SSH Connection Button -->
        <div class="px-3 py-2" style="border-top:1px solid #00ff00;">
            <button @click="showSSHModal = true" class="w-full rounded py-1 text-xs crt-button">
                🔗 SSH Connections
            </button>
        </div>
    </aside>

    <!-- SSH Modal -->
    <div v-if="showSSHModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
        <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:500px;max-height:80vh;overflow-y:auto;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold crt-glow">SSH Connections</h3>
                <button @click="showSSHModal = false" class="text-xs crt-button">✕</button>
            </div>

            <!-- Add SSH Connection Form -->
            <div class="mb-4 p-3" style="background:#001100;border:1px solid #00ff00;">
                <h4 class="text-xs font-bold mb-2 crt-text">Add New SSH Connection</h4>
                <div class="flex flex-col gap-2">
                    <input v-model="sshForm.name" placeholder="Connection Name" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-model="sshForm.host" placeholder="SSH Host" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-model="sshForm.user" placeholder="SSH User" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-model="sshForm.port" placeholder="SSH Port (default: 22)" class="crt-input px-2 py-1 text-xs rounded">
                    <select v-model="sshForm.authMethod" class="crt-input px-2 py-1 text-xs rounded">
                        <option value="password">Password Authentication</option>
                        <option value="key">SSH Key Authentication</option>
                    </select>
                    <input v-if="sshForm.authMethod === 'password'" v-model="sshForm.password" type="password" placeholder="SSH Password" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-if="sshForm.authMethod === 'key'" v-model="sshForm.keyPath" placeholder="SSH Key Path (default: ~/.ssh/id_rsa)" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-if="sshForm.authMethod === 'key'" v-model="sshForm.keyPassphrase" type="password" placeholder="Key Passphrase (optional)" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-model="sshForm.remotePath" placeholder="Remote Log Path (e.g., /var/log)" class="crt-input px-2 py-1 text-xs rounded">
                    <div class="flex gap-2">
                        <button @click="testSSHConnection" class="flex-1 crt-button py-1 text-xs rounded">Test Connection</button>
                        <button @click="addSSHConnection" class="flex-1 crt-button py-1 text-xs rounded">Save Connection</button>
                    </div>
                </div>
            </div>

            <!-- Saved SSH Connections -->
            <div>
                <h4 class="text-xs font-bold mb-2 crt-text">Saved Connections</h4>
                <div v-if="sshConnections.length === 0" class="text-xs crt-dim">No SSH connections saved</div>
                <div v-for="(conn, idx) in sshConnections" :key="idx" class="mb-2 p-2" style="background:#001100;border:1px solid #002200;">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs font-bold crt-text">{{ conn.name }}</div>
                            <div class="text-xs crt-dim">{{ conn.user }}@{{ conn.host }}:{{ conn.port || 22 }}</div>
                            <div class="text-xs crt-dim">Path: {{ conn.remotePath }}</div>
                        </div>
                        <div class="flex gap-1">
                            <button @click="connectSSH(idx)" class="crt-button px-2 py-1 text-xs rounded">Connect</button>
                            <button @click="deleteSSHConnection(idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center gap-2 px-4 py-2" style="background:#000;border-bottom:1px solid #00ff00;">
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="rounded px-3 py-1 text-sm flex-1 max-w-xs crt-input">
            <button @click="loadEntries" title="Refresh"
                class="px-3 py-1 rounded text-sm crt-button">↺</button>
            <div class="flex items-center gap-1 rounded overflow-hidden crt-border">
                <button @click="fontSize = Math.max(10, fontSize - 1)"
                    class="px-2 py-1 text-xs crt-button">A−</button>
                <span class="px-2 text-xs crt-dim">{{ fontSize }}px</span>
                <button @click="fontSize = Math.min(24, fontSize + 1)"
                    class="px-2 py-1 text-xs crt-button">A+</button>
            </div>
            <!-- Bookmarks dropdown -->
            <div class="relative" style="margin-left:auto;">
                <button @click="showBookmarks = !showBookmarks"
                    class="px-3 py-1 rounded text-sm flex items-center gap-1 crt-button" style="border-color:#ffff00;color:#ffff00;">
                    ★ <span class="crt-dim">{{ bookmarks.length }}</span>
                </button>
                <div v-if="showBookmarks" class="absolute right-0 top-full mt-1 rounded shadow-lg z-20 overflow-hidden"
                    style="background:#000;border:1px solid #00ff00;width:380px;max-height:320px;overflow-y:auto;">
                    <div v-if="!bookmarks.length" class="px-3 py-2 text-xs crt-dim">Brak zakładek</div>
                    <div v-for="(bm, bi) in bookmarks" :key="bi"
                        class="px-3 py-2 cursor-pointer flex items-center gap-2"
                        style="border-bottom:1px solid #002200;"
                        @click="goToBookmark(bm)">
                        <span class="text-xs font-bold flex-shrink-0" :style="'color:' + levelColor(bm.level)">{{ bm.level }}</span>
                        <span class="text-xs truncate flex-1 crt-text">{{ bm.message }}</span>
                        <span class="text-xs flex-shrink-0 crt-dim">{{ bm.file.split('/').pop() }}</span>
                        <button @click.stop="removeBookmark(bi)" class="text-xs crt-button" style="border-color:#ff0000;color:#ff0000;" title="Usuń">✕</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demo banner -->
        <div v-if="isDemo" class="px-4 py-2 text-xs text-center" style="background:#0a0a00;border-bottom:1px solid #ffff00;color:#ffff00;">
            ⚠ DEMO LOGS — To jest demonstracja. Dodaj katalog logów, wpisz ścieżkę lub połącz się przez SSH, aby wyświetlić prawdziwe logi.
        </div>

        <!-- States -->
        <div v-if="loading" class="flex-1 flex items-center justify-center crt-dim">Ładowanie…</div>
        <div v-else-if="!selectedFile" class="flex-1 flex items-center justify-center crt-dim">Wybierz plik logów z listy, wpisz ścieżkę lub połącz się przez SSH.</div>
        <div v-else-if="!filtered.length" class="flex-1 flex items-center justify-center crt-dim">Brak wpisów pasujących do filtrów.</div>

        <!-- Table -->
        <div v-else class="flex-1 overflow-auto">
            <table id="logsTable" class="w-full text-sm border-collapse display">
                <thead style="background:#001100;border-bottom:1px solid #00ff00;" class="sticky top-0 z-10">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium text-xs crt-dim" style="width:155px;">Datetime</th>
                        <th class="text-left px-3 py-2 font-medium text-xs crt-dim" style="width:90px;">Level</th>
                        <th class="text-left px-3 py-2 font-medium text-xs crt-dim" style="width:200px;">Location</th>
                        <th class="text-left px-3 py-2 font-medium text-xs crt-dim">Message</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(entry, i) in filtered" :key="i">
                        <tr @click="toggle(i)" class="cursor-pointer"
                            :style="'border-bottom:1px solid #002200;' + rowBg(entry.level)">
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">{{ formatDate(entry.datetime) }}</td>
                            <td class="px-3 py-1.5 text-xs font-bold whitespace-nowrap" :style="'color:' + levelColor(entry.level)">{{ entry.level }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">
                                <a v-if="editorUrl && entry.location"
                                   :href="openInEditor(entry.location)"
                                   @click.stop
                                   class="hover:underline" :style="'color:' + levelColor(entry.level)">{{ entry.location }}</a>
                                <span v-else>{{ entry.location }}</span>
                            </td>
                            <td class="px-3 py-1.5 truncate max-w-0 w-full crt-text">
                                <span class="block truncate">
                                    <span v-if="isBookmarked(entry)" style="color:#ffff00;" title="Zakładka">★ </span>{{ entry.message }}
                                </span>
                                <span class="text-xs crt-dim">{{ expanded[i] ? '▲' : '▼' }}</span>
                            </td>
                        </tr>
                        <tr v-if="expanded[i]" style="background:#001100;border-bottom:1px solid #002200;">
                            <td colspan="4" class="px-3 py-2">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <div class="text-sm mb-1 crt-text" style="white-space:pre-wrap;word-break:break-word;">{{ entry.message }}</div>
                                        <div v-if="entry.location" class="text-xs mb-1 crt-dim">📍 {{ entry.location }}</div>
                                        <pre v-if="hasContext(entry)" class="text-xs font-mono whitespace-pre-wrap crt-dim">{{ JSON.stringify(entry.context, null, 2) }}</pre>
                                    </div>
                                    <button @click.stop="toggleBookmark(entry)"
                                        class="text-lg flex-shrink-0 crt-button" :title="isBookmarked(entry) ? 'Usuń zakładkę' : 'Dodaj zakładkę'"
                                        :style="isBookmarked(entry) ? 'border-color:#ffff00;color:#ffff00;' : 'border-color:#006600;color:#006600;'">★</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const { createApp, ref, computed, reactive, watch, nextTick } = Vue;
const EDITOR_URL = <?= json_encode(EDITOR_URL) ?>;

const LEVEL_COLORS = {
    DEBUG:'#00ff00', INFO:'#00ff00', NOTICE:'#00ff00',
    WARNING:'#ffff00', ERROR:'#ff6600', CRITICAL:'#ff0000',
    ALERT:'#ff9900', EMERGENCY:'#ff0066',
};
const LEVEL_DOTS = {
    DEBUG:'#00cc00', INFO:'#00cc00', NOTICE:'#00cc00',
    WARNING:'#cccc00', ERROR:'#cc5200', CRITICAL:'#cc0000',
    ALERT:'#cc7a00', EMERGENCY:'#cc0052',
};
const ROW_BG = {
    ERROR:'background:#0a0500;', CRITICAL:'background:#0a0200;',
    ALERT:'background:#0a0400;', EMERGENCY:'background:#0a0010;',
    WARNING:'background:#0a0a00;',
};

createApp({
    setup() {
        const files        = ref([]);
        const entries      = ref([]);
        const filtered     = ref([]);
        const selectedFile = ref('');
        const selectedDir  = ref('');
        const directories  = ref([]);
        const directFilePath = ref('');
        const allowedDirPath = ref('/var/log');
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
        const bookmarks    = ref(JSON.parse(localStorage.getItem('fplv_bookmarks') || '[]'));
        const showBookmarks = ref(false);
        const MAX_BOOKMARKS = 10;
        let dataTable = null;

        // SSH State
        const showSSHModal = ref(false);
        const sshConnections = ref(JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]'));
        const sshForm = reactive({
            name: '', host: '', user: '', port: '22',
            authMethod: 'password', password: '', keyPath: '', keyPassphrase: '', remotePath: '/var/log'
        });
        const activeSSHConnection = ref(null);
        const sshFiles = ref([]);
        const fileCheckResult = ref(null);
        const isDemo = ref(false);

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

        // Move DEBUG and INFO to top
        const levels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        function openInEditor(location) {
            const [file, line] = location.split(':');
            return editorUrl.value.replace('{file}', encodeURIComponent(file)).replace('{line}', line ?? '1');
        }

        function formatSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
            return (b/1048576).toFixed(1) + ' MB';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr);
                return d.toLocaleString('pl-PL', { 
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            } catch {
                return dateStr;
            }
        }

        async function fetchJson(url) {
            const r = await fetch(url);
            if (!r.ok) throw new Error(await r.text());
            return r.json();
        }

        async function loadFiles() {
            const dirParam = selectedDir.value ? '&dir=' + encodeURIComponent(selectedDir.value) : '';
            files.value = await fetchJson('?action=files' + dirParam);
            if (files.value.length) {
                selectedFile.value = files.value[0].file;
                await loadEntries();
            } else {
                selectedFile.value = '';
                entries.value = [];
                filtered.value = [];
            }
        }

        async function checkDirectFile() {
            const path = directFilePath.value.trim();
            if (!path) { alert('Wpisz ścieżkę do pliku'); return; }
            try {
                const res = await fetchJson('?action=check-file&file=' + encodeURIComponent(path));
                fileCheckResult.value = res;
            } catch (e) {
                fileCheckResult.value = { exists: false, readable: false, size: 0 };
            }
        }

        async function loadDirectFile() {
            if (!directFilePath.value.trim()) {
                alert('Wpisz ścieżkę do pliku');
                return;
            }
            const path = directFilePath.value.trim();
            isDemo.value = false;

            try {
                loading.value = true;
                // Use read-file endpoint that auto-adds dir to allowed list
                const url = '?action=read-file&file=' + encodeURIComponent(path);
                entries.value = await fetchJson(url);
                selectedFile.value = path;
                filtered.value = entries.value;
                applyFilters();
                fileCheckResult.value = null;
            } catch (e) {
                alert('Błąd ładowania pliku: ' + e.message);
                console.error('Load direct file error:', e);
            } finally {
                loading.value = false;
            }
        }

        async function addAllowedDir() {
            if (!allowedDirPath.value.trim()) {
                alert('Wpisz ścieżkę katalogu');
                return;
            }
            const dir = allowedDirPath.value.trim();

            try {
                loading.value = true;
                const res = await fetch('?action=config-add-dir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: 'allowed_' + Date.now(), path: dir })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Katalog dodany: ' + dir);
                    await loadDirectories(); // Reload directories
                } else {
                    alert('Błąd: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Błąd dodawania katalogu: ' + e.message);
            } finally {
                loading.value = false;
            }
        }

        async function cleanupDuplicates() {
            try {
                loading.value = true;
                const res = await fetch('?action=config-cleanup-duplicates', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    alert('Usunięto duplikaty: ' + data.removed);
                    await loadDirectories();
                } else {
                    alert('Błąd: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Błąd czyszczenia: ' + e.message);
            } finally {
                loading.value = false;
            }
        }

        async function changeDir() {
            selectedFile.value = '';
            entries.value = [];
            filtered.value = [];
            await loadFiles();
        }

        async function selectFile(path) {
            // Check if this is an SSH file
            const sshFile = files.value.find(f => f.file === path && f.ssh);
            if (sshFile && sshFile.sshConn) {
                await loadSSHFile(sshFile.sshConn, sshFile.sshRealPath);
                return;
            }
            isDemo.value = false;
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
                    if (!e.datetime) return true; // Skip date filter for entries without datetime
                    const d = e.datetime.slice(0, 10);
                    if (dateFrom.value && d < dateFrom.value) return false;
                    if (dateTo.value   && d > dateTo.value)   return false;
                    return true;
                });
            }
            if (timeFrom.value || timeTo.value) {
                r = r.filter(e => {
                    if (!e.datetime) return true; // Skip time filter for entries without datetime
                    const t = e.datetime.slice(11, 16);
                    if (timeFrom.value && t < timeFrom.value) return false;
                    if (timeTo.value   && t > timeTo.value)   return false;
                    return true;
                });
            }
            if (sortOrder.value === 'asc') r = [...r].reverse();
            filtered.value = r;
            initDataTable();
        }

        function initDataTable() {
            nextTick(() => {
                const table = document.getElementById('logsTable');
                if (!table) return;

                if (dataTable) {
                    dataTable.destroy();
                }

                dataTable = $('#logsTable').DataTable({
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100],
                    order: [[0, 'desc']],
                    language: {
                        search: "Szukaj:",
                        lengthMenu: "Pokaż _MENU_ wpisów",
                        info: "Pokazano _START_ do _END_ z _TOTAL_ wpisów",
                        paginate: {
                            first: "Pierwsza",
                            last: "Ostatnia",
                            next: "Następna",
                            previous: "Poprzednia"
                        }
                    },
                    columnDefs: [
                        { orderable: true, targets: [0, 1, 2, 3] }
                    ]
                });
            });
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

        function bookmarkKey(entry) {
            return entry.datetime + '|' + entry.message.slice(0, 80);
        }

        function isBookmarked(entry) {
            const key = bookmarkKey(entry);
            return bookmarks.value.some(b => b.key === key);
        }

        function toggleBookmark(entry) {
            const key = bookmarkKey(entry);
            const idx = bookmarks.value.findIndex(b => b.key === key);
            if (idx >= 0) {
                bookmarks.value.splice(idx, 1);
            } else {
                if (bookmarks.value.length >= MAX_BOOKMARKS) {
                    bookmarks.value.shift();
                }
                bookmarks.value.push({
                    key,
                    file: selectedFile.value,
                    datetime: entry.datetime,
                    level: entry.level,
                    message: entry.message.slice(0, 120),
                    location: entry.location,
                });
            }
            localStorage.setItem('fplv_bookmarks', JSON.stringify(bookmarks.value));
        }

        function removeBookmark(idx) {
            bookmarks.value.splice(idx, 1);
            localStorage.setItem('fplv_bookmarks', JSON.stringify(bookmarks.value));
        }

        async function goToBookmark(bm) {
            showBookmarks.value = false;
            try {
                const res = await fetch('?action=files' + (selectedDir.value ? '&dir=' + encodeURIComponent(selectedDir.value) : ''));
                const allFiles = await res.json();
                if (!allFiles.some(f => f.file === bm.file)) {
                    alert('Plik już nie istnieje: ' + bm.file.split('/').pop());
                    removeBookmark(bookmarks.value.findIndex(b => b.key === bm.key));
                    return;
                }
            } catch(e) {}
            await selectFile(bm.file);
            // Find and expand the matching entry
            const idx = filtered.value.findIndex(e => bookmarkKey(e) === bm.key);
            if (idx >= 0) {
                expanded[idx] = true;
                await nextTick();
                const rows = document.querySelectorAll('tbody tr');
                // Each entry has 1-2 rows (main + expanded), find the right one
                let rowIdx = 0;
                for (let j = 0; j < idx; j++) {
                    rowIdx++;
                    if (expanded[j]) rowIdx++;
                }
                if (rows[rowIdx]) rows[rowIdx].scrollIntoView({ block: 'center' });
            }
        }

        async function validateBookmarks() {
            try {
                const res = await fetch('?action=files' + (selectedDir.value ? '&dir=' + encodeURIComponent(selectedDir.value) : ''));
                const allFiles = await res.json();
                const validPaths = new Set(allFiles.map(f => f.file));
                const valid = bookmarks.value.filter(b => validPaths.has(b.file));
                if (valid.length !== bookmarks.value.length) {
                    bookmarks.value = valid;
                    localStorage.setItem('fplv_bookmarks', JSON.stringify(valid));
                }
            } catch(e) {}
        }

        async function loadDemoEntries() {
            try {
                loading.value = true;
                entries.value = await fetchJson('?action=demo-entries');
                selectedFile.value = 'demo-logs';
                isDemo.value = true;
                applyFilters();
            } catch(e) {
                console.error('Demo load error:', e);
            } finally {
                loading.value = false;
            }
        }

        async function loadSSHFile(conn, filePath) {
            if (!conn) return;
            const password = activeSSHConnection.value?.password || prompt(`Podaj hasło SSH dla ${conn.name} (lub zostaw puste dla klucza):`);
            if (password === null) return;

            try {
                loading.value = true;
                isDemo.value = false;
                const payload = {
                    ssh_host: conn.host,
                    ssh_user: conn.user,
                    ssh_port: parseInt(conn.port) || 22,
                    ssh_auth_method: password ? 'password' : (conn.authMethod || 'key'),
                    ssh_password: password || undefined,
                    ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                    path: filePath,
                };

                const res = await fetch('?action=ssh-read-file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success && data.entries) {
                    entries.value = data.entries;
                    selectedFile.value = '[SSH] ' + conn.name + ':' + filePath;
                    applyFilters();
                } else {
                    alert('Błąd odczytu pliku SSH: ' + (data.error || 'Nieznany błąd'));
                }
            } catch(e) {
                alert('Błąd SSH: ' + e.message);
            } finally {
                loading.value = false;
            }
        }

        async function init() {
            try {
                directories.value = await fetchJson('?action=directories');
                if (directories.value.length) {
                    selectedDir.value = directories.value[0].key;
                }
            } catch(e) { /* fallback: no dirs endpoint = single dir mode */ }
            await loadFiles();

            // First-run fallback: no files found → try scan → demo
            if (!files.value.length) {
                try {
                    const scanResult = await fetchJson('?action=scan-directories');
                    const scanDirs = Object.values(scanResult);
                    if (scanDirs.length > 0) {
                        // Found log dirs - add them and reload
                        for (const sd of scanDirs) {
                            try {
                                await fetch('?action=config-add-dir', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ name: sd.name, path: sd.path, type: 'local' })
                                });
                            } catch(e) { /* skip duplicates */ }
                        }
                        await loadDirectories();
                        await loadFiles();
                    }
                } catch(e) { /* scan failed - continue to demo */ }
            }

            // Still no files → load demo
            if (!files.value.length && !selectedFile.value) {
                await loadDemoEntries();
            }

            validateBookmarks();
        }

        async function loadDirectories() {
            try {
                directories.value = await fetchJson('?action=directories');
                if (directories.value.length) {
                    selectedDir.value = directories.value[0].key;
                }
            } catch(e) { /* fallback */ }
        }

        init();

        // SSH Functions
        async function testSSHConnection() {
            try {
                const payload = {
                    ssh_host: sshForm.host,
                    ssh_user: sshForm.user,
                    ssh_port: parseInt(sshForm.port) || 22,
                    ssh_auth_method: sshForm.authMethod,
                    ssh_password: sshForm.authMethod === 'password' ? sshForm.password : undefined,
                    ssh_key_path: sshForm.authMethod === 'key' ? sshForm.keyPath : undefined,
                    ssh_key_passphrase: sshForm.authMethod === 'key' ? sshForm.keyPassphrase : undefined,
                };

                const res = await fetch('?action=ssh-test-connection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    alert('SSH connection successful!');
                } else {
                    alert('SSH connection failed: ' + (data.error || 'Unknown error'));
                }
            } catch(e) {
                alert('SSH connection failed: ' + e.message);
            }
        }

        function addSSHConnection() {
            if (!sshForm.name || !sshForm.host || !sshForm.user) {
                alert('Please fill in name, host, and user');
                return;
            }

            const conn = {
                name: sshForm.name,
                host: sshForm.host,
                user: sshForm.user,
                port: sshForm.port || 22,
                authMethod: sshForm.authMethod,
                remotePath: sshForm.remotePath || '/var/log',
                // Note: We don't save passwords for security
            };

            sshConnections.value.push(conn);
            localStorage.setItem('fplv_ssh_connections', JSON.stringify(sshConnections.value));

            // Reset form
            Object.assign(sshForm, {
                name: '', host: '', user: '', port: '22',
                authMethod: 'password', password: '', keyPath: '', keyPassphrase: '', remotePath: '/var/log'
            });

            alert('SSH connection saved! Note: Password is not saved for security.');
        }

        function deleteSSHConnection(idx) {
            if (confirm('Delete this SSH connection?')) {
                sshConnections.value.splice(idx, 1);
                localStorage.setItem('fplv_ssh_connections', JSON.stringify(sshConnections.value));
            }
        }

        async function connectSSH(idx) {
            const conn = sshConnections.value[idx];
            const password = prompt(`Podaj hasło SSH dla ${conn.name} (lub zostaw puste dla klucza):`);

            if (password === null) return;

            try {
                loading.value = true;
                const payload = {
                    ssh_host: conn.host,
                    ssh_user: conn.user,
                    ssh_port: parseInt(conn.port) || 22,
                    ssh_auth_method: password ? 'password' : (conn.authMethod || 'key'),
                    ssh_password: password || undefined,
                    ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                    path: conn.remotePath,
                };

                const res = await fetch('?action=ssh-list-files', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    activeSSHConnection.value = { ...conn, password };
                    sshFiles.value = data.files;
                    isDemo.value = false;

                    // Add SSH files to sidebar file list with [SSH] prefix
                    data.files.forEach(file => {
                        const sshPath = '[SSH:' + conn.name + '] ' + file.path;
                        if (!files.value.some(f => f.file === sshPath)) {
                            files.value.unshift({
                                file: sshPath,
                                date: new Date().toISOString().split('T')[0],
                                size: file.size || 0,
                                ssh: true,
                                sshConn: conn,
                                sshRealPath: file.path,
                            });
                        }
                    });

                    showSSHModal.value = false;
                } else {
                    alert('Błąd listowania plików: ' + (data.error || 'Nieznany błąd'));
                }
            } catch(e) {
                alert('Błąd połączenia SSH: ' + e.message);
            } finally {
                loading.value = false;
            }
        }

        return {
            files, entries, filtered, selectedFile, filterText, loading, expanded,
            levels, levelCounts, dateFrom, dateTo, timeFrom, timeTo, sortOrder, fontSize,
            excludedLevels, editorUrl, directories, selectedDir, directFilePath, allowedDirPath,
            bookmarks, showBookmarks, fileCheckResult, isDemo,
            showSSHModal, sshConnections, sshForm, sshFiles, activeSSHConnection,
            selectFile, loadEntries, applyFilters, toggle, toggleSort, toggleLevel,
            changeDir, formatSize, formatDate, levelColor, levelDot, rowBg, hasContext, openInEditor,
            toggleBookmark, isBookmarked, removeBookmark, goToBookmark,
            testSSHConnection, addSSHConnection, deleteSSHConnection, connectSSH,
            loadDirectFile, checkDirectFile, addAllowedDir, cleanupDuplicates, loadDirectories, loadDemoEntries,
        };
    }
}).mount('#app');
</script>
</body>
</html>
