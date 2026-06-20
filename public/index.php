<?php
if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}
if (!defined('EDITOR_URL')) {
    define('EDITOR_URL', getenv('EDITOR_URL') ?: 'phpstorm://open?file={file}&line={line}');
}
if (isset($_GET['action'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = \Mariusz\LogViewer\Bootstrap\AppBootstrap::create();
    
    // Check if we have a matching route in Slim, otherwise fall back to old controller
    $request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
    $routeContext = \Slim\Routing\RouteContext::fromRequest($request);
    
    // We can't easily check for route existence without running the app or doing more complex stuff
    // For now, let's just use Slim for /api routes and fallback to old controller for everything else
    if (str_starts_with($request->getUri()->getPath(), '/api')) {
        $app->run();
        exit;
    }

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
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.FPLV_CONFIG = {
            editorUrl: <?= json_encode(EDITOR_URL) ?>
        };
    </script>
</head>
<body class="h-screen overflow-hidden crt-text crt-bg">

<div id="app" v-cloak class="flex h-screen" :style="{ fontSize: fontSize + 'px' }">

    <!-- Sidebar -->
    <aside style="width:350px;min-width:350px;background:#000;border-right:1px solid #00ff00;" class="flex flex-col">
        <div class="px-3 py-3 crt-border" style="border-bottom:1px solid #00ff00;">
            <div class="font-bold text-sm crt-glow">⚡ LOG-VIEWER</div>
        </div>

        <!-- Stats -->
        <div style="border-top:1px solid #00ff00;" class="px-3 py-2">
            <div class="text-xs crt-dim">
                {{ filtered.length }} entries<br>
                <span v-if="selectedFile">{{ selectedFile.split('/').pop() }}</span>
            </div>
        </div>

        <!-- Directory selector -->
        <div v-if="directories.length > 1" class="px-3 py-2" style="border-bottom:1px solid #00ff00;">
            <div class="text-xs font-semibold mb-1 crt-dim">KATALOG</div>
            <div class="flex gap-2">
                <select v-model="selectedDir" @change="changeDir"
                        class="flex-1 rounded px-2 py-1 text-xs crt-input">
                    <option v-for="d in directories" :key="d.key" :value="d.key">{{ d.key }}</option>
                </select>
                <button v-if="selectedDir && selectedDir.startsWith('ssh:')" @click="refreshSSHDir(selectedDir)"
                        class="px-2 py-1 text-xs crt-button" title="Odśwież">
                    ↻
                </button>
            </div>
        </div>

        <!-- File list -->
        <div class="flex-1 overflow-y-auto" style="flex:6;">
            <div v-if="files.length === 0" class="px-3 py-8 text-center crt-dim" style="font-size:12px;">
                pusto
            </div>
            <div v-for="f in files" :key="f.file"
                @click="selectFile(f.file)"
                class="px-3 py-2 cursor-pointer"
                style="border-bottom:1px solid #002200;"
                :style="selectedFile === f.file
                    ? 'background:#002200;border-left:3px solid #00ff00;color:#00ff00;'
                    : 'color:#006600;border-left:3px solid transparent;'">
                <div class="font-medium truncate" style="font-size:10px;">{{ f.file.split('/').pop() }}</div>
                <div class="crt-dim" style="font-size:10px;">{{ formatDate(f.date) }} · {{ formatSize(f.size) }}</div>
                <div v-if="f.allow" class="crt-dim" style="font-size:10px;">allow: {{ f.allow }}</div>
            </div>
        </div>

        <!-- Direct file path -->
        <div class="px-3 py-3" style="border-bottom:1px solid #00ff00;background:#001100;">
            <div class="text-xs font-bold mb-2 crt-text">📂 ŚCIEŻKA DO PLIKU</div>
            <input type="text" v-model="directFilePath" placeholder="/var/log/php/php_errors.log"
                class="w-full rounded px-2 py-1 text-xs crt-input mb-2">
            <button @click="loadDirectFile" class="w-full rounded px-2 py-1 text-xs crt-button font-bold">
                ⚡ ZAŁADUJ
            </button>
        </div>

        <!-- Add allowed directory -->
        <div class="px-3 py-2" style="border-bottom:1px solid #00ff00;">
            <div class="text-xs font-semibold mb-1 crt-dim">➕ DODAJ KATALOG DOZWOLONY</div>
            <input type="text" v-model="allowedDirPath" placeholder="/var/log"
                class="w-full rounded px-2 py-1 text-xs crt-input mb-2">
            <button @click="addAllowedDir" class="w-full rounded px-2 py-1 text-xs crt-button mb-2">
                DODAJ
            </button>
            <button @click="cleanupDuplicates" class="w-full rounded px-2 py-1 text-xs crt-button crt-dim mb-1">
                🧹 Czyść duplikaty
            </button>
            <button @click="cleanupAllowed" class="w-full rounded px-2 py-1 text-xs crt-button crt-dim">
                🧹 Czyść nazwy allowed_*
            </button>
        </div>

        <!-- SSH Connection Button -->
        <div class="px-3 py-2" style="border-top:1px solid #00ff00;">
            <button @click="showSSHModal = true; cancelEdit()" class="w-full rounded py-1 text-xs crt-button">
                🔗 SSH Connections
            </button>
        </div>
    </aside>

    <!-- SSH Modal -->
    <div v-if="showSSHModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
        <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:500px;max-height:80vh;overflow-y:auto;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold crt-glow">SSH Connections</h3>
                <button @click="showSSHModal = false; cancelEdit()" class="text-xs crt-button">✕</button>
            </div>

            <!-- Add SSH Connection Form -->
            <div class="mb-4 p-3" style="background:#001100;border:1px solid #00ff00;">
                <h4 class="text-xs font-bold mb-2 crt-text">{{ editingIndex >= 0 ? 'Edit SSH Connection' : 'Add New SSH Connection' }}</h4>
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
                    <label class="flex items-center gap-2 text-xs crt-text">
                        <input type="checkbox" v-model="sshForm.allFiles" class="crt-input">
                        Show all files (no pattern filtering)
                    </label>
                    <div class="flex gap-2">
                        <button @click="testSSHConnection" class="flex-1 crt-button py-1 text-xs rounded">Test Connection</button>
                        <button v-if="editingIndex >= 0" @click="cancelEdit" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                        <button @click="addSSHConnection" class="flex-1 crt-button py-1 text-xs rounded">{{ editingIndex >= 0 ? 'Update Connection' : 'Save Connection' }}</button>
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
                            <button @click="addManualSSHFile(idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#00ffff;color:#00ffff;">Download File</button>
                            <button @click="editSSHConnection(idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ffff00;color:#ffff00;">Edit</button>
                            <button @click="deleteSSHConnection(idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SSH Password Modal -->
    <div v-if="showPasswordModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
        <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:400px;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold crt-glow">SSH Password</h3>
                <button @click="cancelPasswordModal" class="text-xs crt-button">✕</button>
            </div>
            <div class="mb-4">
                <label class="block text-xs crt-text mb-2">Enter SSH password (or leave empty for key auth):</label>
                <input v-model="passwordForConnection" type="password" placeholder="Password" class="crt-input px-2 py-1 text-xs rounded w-full" @keyup.enter="executeSSHConnection">
            </div>
            <div class="flex gap-2">
                <button @click="cancelPasswordModal" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                <button @click="executeSSHConnection" class="flex-1 crt-button py-1 text-xs rounded">Connect</button>
            </div>
        </div>
    </div>

    <!-- Manual SSH File Modal -->
    <div v-if="showManualFileModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
        <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ffff;width:400px;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold crt-glow" style="color:#00ffff;">Download SSH File</h3>
                <button @click="cancelManualFileModal" class="text-xs crt-button">✕</button>
            </div>
            <div class="mb-4">
                <label class="block text-xs crt-text mb-2">SSH Password:</label>
                <input v-model="passwordForConnection" type="password" placeholder="Enter password" class="crt-input px-2 py-1 text-xs rounded w-full mb-3">
                <label class="block text-xs crt-text mb-2">Remote file path:</label>
                <input v-model="manualFilePath" placeholder="/var/log/demo.log" class="crt-input px-2 py-1 text-xs rounded w-full" @keyup.enter="executeManualFileAdd">
                <p class="text-xs crt-dim mt-2">File will be downloaded to temp/ and added to file list</p>
            </div>
            <div class="flex gap-2">
                <button @click="cancelManualFileModal" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                <button @click="executeManualFileAdd" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#00ffff;color:#00ffff;">Download</button>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center gap-2 px-4 py-2" style="background:#000;border-bottom:1px solid #00ff00;">
            <input v-model="filterText" @input="applyFilters" placeholder="Search…"
                class="rounded px-3 py-1 text-sm flex-1 max-w-xs crt-input">

            <!-- Level quick filters (DEBUG, INFO, NOTICE) -->
            <div class="flex items-center gap-1">
                <button v-for="level in ['DEBUG','INFO','NOTICE']" :key="level"
                        @click="toggleLevel(level)"
                        class="px-2 py-1 text-xs rounded crt-button"
                        :style="excludedLevels.includes(level) ? 'border-color:#003300;color:#003300;' : 'border-color:' + levelDot(level) + ';color:' + levelColor(level)">
                    {{ level }}
                </button>
            </div>

            <!-- Filters: Sort, Date, Levels -->
            <div class="flex items-center gap-2">
                <!-- Levels dropdown with Sort and Date -->
                <div class="relative">
                    <button @click="showLevelFilters = !showLevelFilters" class="px-2 py-1 text-xs crt-button">
                        FILTRY ▼
                    </button>
                    <div v-if="showLevelFilters" class="absolute left-0 top-full mt-1 rounded shadow-lg z-20 p-3"
                         style="background:#000;border:1px solid #00ff00;min-width:250px;">
                        <!-- Sort -->
                        <div class="mb-3 pb-2" style="border-bottom:1px solid #002200;">
                            <div class="text-xs crt-dim mb-1">Sortowanie</div>
                            <button @click="toggleSort" class="w-full px-2 py-1 text-xs crt-button">
                                {{ sortOrder === 'desc' ? '↓ Najnowsze na górze' : '↑ Najstarsze na górze' }}
                            </button>
                        </div>
                        <!-- Date range -->
                        <div class="mb-3 pb-2" style="border-bottom:1px solid #002200;">
                            <div class="text-xs crt-dim mb-1">Zakres daty</div>
                            <div class="flex items-center gap-1 mb-1">
                                <input type="date" v-model="dateFrom" @change="applyFilters"
                                       class="px-1 py-0.5 text-xs crt-input flex-1">
                            </div>
                            <div class="flex items-center gap-1">
                                <input type="date" v-model="dateTo" @change="applyFilters"
                                       class="px-1 py-0.5 text-xs crt-input flex-1">
                            </div>
                        </div>
                        <!-- All Levels -->
                        <div class="text-xs crt-dim mb-1">Poziomy logów</div>
                        <label v-for="level in levels" :key="level"
                               class="flex items-center gap-2 text-xs cursor-pointer crt-dim mb-1">
                            <span class="w-2 h-2 rounded-full inline-block"
                                  :style="'background:' + levelDot(level)"></span>
                            <input type="checkbox" :checked="!excludedLevels.includes(level)"
                                   @change="toggleLevel(level)" class="hidden">
                            <span @click="toggleLevel(level)"
                                  :style="excludedLevels.includes(level) ? 'color:#003300;' : 'color:#00ff00;'">
                                {{ level }}
                            </span>
                            <span class="ml-auto crt-dim">{{ levelCounts[level] || '' }}</span>
                        </label>
                    </div>
                </div>
            </div>

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

        <!-- States -->
        <div v-if="loading" class="flex-1 flex items-center justify-center crt-dim">Loading…</div>
        <div v-else-if="!selectedFile" class="flex-1 flex items-center justify-center crt-dim">Select a log file.</div>
        <div v-else-if="!filtered.length" class="flex-1 flex items-center justify-center crt-dim">No entries match filters.</div>

        <!-- DataTable -->
        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <!-- DataTable Toolbar -->
            <div class="flex items-center justify-between px-4 py-2"
                 style="background:#001100;border-bottom:1px solid #002200;">
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">Pokaż:</span>
                    <select v-model="tablePageSize" @change="setTablePageSize(parseInt($event.target.value))"
                            class="crt-input text-xs px-2 py-1 rounded">
                        <option v-for="size in TABLE_PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
                    </select>
                    <span class="text-xs crt-dim">na stronę</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">{{ tableStartRow }}-{{ tableEndRow }} z {{ filtered.length }}</span>
                    <button @click="tablePrevPage" :disabled="tablePage === 1" class="px-2 py-1 text-xs crt-button"
                            :style="tablePage === 1 ? 'opacity:0.3;cursor:not-allowed;' : ''">←
                    </button>
                    <span class="text-xs crt-text">Strona {{ tablePage }} / {{ tableTotalPages }}</span>
                    <button @click="tableNextPage" :disabled="tablePage === tableTotalPages"
                            class="px-2 py-1 text-xs crt-button"
                            :style="tablePage === tableTotalPages ? 'opacity:0.3;cursor:not-allowed;' : ''">→
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-auto">
                <table class="w-full text-sm border-collapse">
                    <thead style="background:#001100;border-bottom:1px solid #00ff00;" class="sticky top-0 z-10">
                    <tr>
                        <th @click="toggleTableSort('datetime')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow"
                            style="width:155px;">
                            Datetime {{ tableSortColumn === 'datetime' ? (tableSortDirection === 'asc' ? '↑' : '↓') : ''
                            }}
                        </th>
                        <th @click="toggleTableSort('level')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow"
                            style="width:90px;">
                            Level {{ tableSortColumn === 'level' ? (tableSortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th @click="toggleTableSort('location')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow"
                            style="width:200px;">
                            Location {{ tableSortColumn === 'location' ? (tableSortDirection === 'asc' ? '↑' : '↓') : ''
                            }}
                        </th>
                        <th @click="toggleTableSort('message')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow">
                            Message {{ tableSortColumn === 'message' ? (tableSortDirection === 'asc' ? '↑' : '↓') : ''}}
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="(entry, i) in tablePaginatedData" :key="tableStartRow + i - 1">
                        <tr @click="toggle(tableStartRow + i - 1)" class="cursor-pointer"
                            :style="'border-bottom:1px solid #002200;' + rowBg(entry.level)">
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">
                                {{ formatDate(entry.datetime) }}
                            </td>
                            <td class="px-3 py-1.5 text-xs font-bold whitespace-nowrap"
                                :style="'color:' + levelColor(entry.level)">{{ entry.level }}
                            </td>
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">
                                <a v-if="editorUrl && entry.location"
                                   :href="openInEditor(entry.location)"
                                   @click.stop
                                   class="hover:underline" :style="'color:' + levelColor(entry.level)">{{ entry.location
                                    }}</a>
                                <span v-else>{{ entry.location }}</span>
                            </td>
                            <td class="px-3 py-1.5 truncate max-w-0 w-full crt-text">
                                    <span class="block truncate">
                                        <span v-if="isBookmarked(entry)" style="color:#ffff00;"
                                              title="Zakładka">★ </span>{{ entry.message }}
                                    </span>
                                <span class="text-xs crt-dim">{{ expanded[tableStartRow + i - 1] ? '▲' : '▼' }}</span>
                            </td>
                        </tr>
                        <tr v-if="expanded[tableStartRow + i - 1]"
                            style="background:#001100;border-bottom:1px solid #002200;">
                            <td colspan="4" class="px-3 py-2">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <div class="text-sm mb-1 crt-text"
                                             style="white-space:pre-wrap;word-break:break-word;">{{ entry.message }}
                                        </div>
                                        <div v-if="entry.location" class="text-xs mb-1 crt-dim">📍 {{ entry.location}}
                                        </div>
                                        <pre v-if="hasContext(entry)"
                                             class="text-xs font-mono whitespace-pre-wrap crt-dim">{{ JSON.stringify(entry.context, null, 2) }}</pre>
                                    </div>
                                    <button @click.stop="toggleBookmark(entry)"
                                            class="text-lg flex-shrink-0 crt-button"
                                            :title="isBookmarked(entry) ? 'Usuń zakładkę' : 'Dodaj zakładkę'"
                                            :style="isBookmarked(entry) ? 'border-color:#ffff00;color:#ffff00;' : 'border-color:#006600;color:#006600;'">
                                        ★
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>

            <!-- DataTable Footer -->
            <div class="flex items-center justify-between px-4 py-2"
                 style="background:#001100;border-top:1px solid #002200;">
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">Idź do strony:</span>
                    <input type="number" v-model.number="tablePage"
                           @change="setTablePage(Math.max(1, Math.min(tableTotalPages, $event.target.value)))" min="1"
                           :max="tableTotalPages" class="crt-input text-xs px-2 py-1 rounded" style="width:60px;">
                </div>
                <div class="flex items-center gap-1">
                    <button v-for="p in Math.min(10, tableTotalPages)" :key="p" @click="setTablePage(p)"
                            class="px-2 py-1 text-xs crt-button"
                            :style="p === tablePage ? 'background:#00ff00;color:#000;' : ''">{{ p }}
                    </button>
                    <span v-if="tableTotalPages > 10" class="text-xs crt-dim">... ({{ tableTotalPages }})</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
