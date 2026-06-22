/**
 * fast-php-log-viewer - Vue.js Application
 */

const {createApp, ref, computed, reactive, watch, nextTick} = Vue;

const EDITOR_URL = window.FPLV_CONFIG?.editorUrl || 'phpstorm://open?file={file}&line={line}';

const LEVEL_COLORS = {
    DEBUG: '#00ff00', INFO: '#00ff00', NOTICE: '#00ff00',
    WARNING: '#ffff00', ERROR: '#ff6600', CRITICAL: '#ff0000',
    ALERT: '#ff9900', EMERGENCY: '#ff0066',
};

const LEVEL_DOTS = {
    DEBUG: '#00cc00', INFO: '#00cc00', NOTICE: '#00cc00',
    WARNING: '#cccc00', ERROR: '#cc5200', CRITICAL: '#cc0000',
    ALERT: '#cc7a00', EMERGENCY: '#cc0052',
};

const ROW_BG = {
    ERROR: 'background:#0a0500;', CRITICAL: 'background:#0a0200;',
    ALERT: 'background:#0a0400;', EMERGENCY: 'background:#0a0010;',
    WARNING: 'background:#0a0a00;',
};

// Move DEBUG and INFO to top
const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

createApp({
    setup() {
        const files = ref([]);
        const entries = ref([]);
        const filtered = ref([]);
        const selectedFile = ref('');
        const selectedDir = ref('');
        const directories = ref([]);
        const directFilePath = ref('');
        const allowedDirPath = ref('/var/log');
        const filterText = ref('');
        const loading = ref(false);
        const expanded = reactive({});
        const excludedLevels = ref([]);
        const sortOrder = ref('desc');
        const dateFrom = ref('');
        const dateTo = ref('');
        const timeFrom = ref('');
        const timeTo = ref('');
        const editorUrl = ref(EDITOR_URL);
        const fontSize = ref(parseInt(localStorage.getItem('fplv_fontsize') || '13'));
        const bookmarks = ref(JSON.parse(localStorage.getItem('fplv_bookmarks') || '[]'));
        const showBookmarks = ref(false);
        const showLevelFilters = ref(false);
        const MAX_BOOKMARKS = 10;

        // DataTable State
        const tableSortColumn = ref('datetime');
        const tableSortDirection = ref('desc');
        const tablePage = ref(1);
        const tablePageSize = ref(100);
        const TABLE_PAGE_SIZES = [50, 100, 250, 500, 1000];

        // Setup Wizard State
        const showSetupWizard = ref(false);
        const setupSteps = ref([]);
        const currentSetupStep = ref('');
        const setupSkipConfirm = ref(false);
        const setupStepData = reactive({});
        const setupWarning = ref('');
        const sshEnabled = ref(true);

        // SSH State
        const showSSHModal = ref(false);
        const showPasswordModal = ref(false);
        const showManualFileModal = ref(false);
        const passwordForConnection = ref('');
        const manualFilePath = ref('');
        const connectingConnectionIndex = ref(-1);
        const sshConnections = ref(JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]'));
        const sshFiles = ref({});
        const editingIndex = ref(-1);
        const sshForm = reactive({
            name: '',
            host: '',
            user: '',
            port: '22',
            authMethod: 'password',
            password: '',
            keyPath: '',
            keyPassphrase: '',
            remotePath: '/var/log',
            allFiles: false
        });

        watch(fontSize, v => localStorage.setItem('fplv_fontsize', String(v)));

        const levelCounts = computed(() => {
            const c = {};
            for (const e of entries.value) c[e.level] = (c[e.level] ?? 0) + 1;
            return c;
        });

        // DataTable computed properties
        const tableSortedData = computed(() => {
            let data = [...filtered.value];
            const col = tableSortColumn.value;
            const dir = tableSortDirection.value;

            data.sort((a, b) => {
                let valA = a[col] || '';
                let valB = b[col] || '';

                // Special handling for level column (custom order)
                if (col === 'level') {
                    const order = {
                        DEBUG: 1,
                        INFO: 2,
                        NOTICE: 3,
                        WARNING: 4,
                        ERROR: 5,
                        CRITICAL: 6,
                        ALERT: 7,
                        EMERGENCY: 8
                    };
                    valA = order[valA] || 99;
                    valB = order[valB] || 99;
                }

                if (valA < valB) return dir === 'asc' ? -1 : 1;
                if (valA > valB) return dir === 'asc' ? 1 : -1;
                return 0;
            });

            return data;
        });

        const tableTotalPages = computed(() => {
            return Math.ceil(tableSortedData.value.length / tablePageSize.value) || 1;
        });

        const tablePaginatedData = computed(() => {
            const start = (tablePage.value - 1) * tablePageSize.value;
            const end = start + tablePageSize.value;
            return tableSortedData.value.slice(start, end);
        });

        const tableStartRow = computed(() => {
            return filtered.value.length === 0 ? 0 : (tablePage.value - 1) * tablePageSize.value + 1;
        });

        const tableEndRow = computed(() => {
            return Math.min(tablePage.value * tablePageSize.value, tableSortedData.value.length);
        });

        const levelColor = l => LEVEL_COLORS[l] ?? '#9ca3af';
        const levelDot = l => LEVEL_DOTS[l] ?? '#6b7280';
        const rowBg = l => ROW_BG[l] ?? '';
        const hasContext = e => e.context && Object.keys(e.context).length > 0;
        const levels = LEVELS;

        function openInEditor(location) {
            const [file, line] = location.split(':');
            return editorUrl.value.replace('{file}', encodeURIComponent(file)).replace('{line}', line ?? '1');
        }

        function formatSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            return (b / 1048576).toFixed(1) + ' MB';
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

        function syncSSHDirs() {
            const conns = JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]');
            for (const conn of conns) {
                const key = 'ssh:' + conn.name;
                if (!directories.value.some(d => d.key === key)) {
                    directories.value.push({key, path: key, name: 'ssh-' + conn.name});
                }
            }
        }

        async function loadFiles() {
            if (selectedDir.value && selectedDir.value.startsWith('ssh:')) {
                const connName = selectedDir.value.replace('ssh:', '');
                files.value = sshFiles.value[connName] || [];
                if (files.value.length) {
                    selectedFile.value = files.value[0].file;
                    await loadEntries();
                } else {
                    selectedFile.value = '';
                    entries.value = [];
                    filtered.value = [];
                }
                return;
            }
            const dirParam = selectedDir.value ? '?dir=' + encodeURIComponent(selectedDir.value) : '';
            files.value = await fetchJson('/api/files' + dirParam);
            if (files.value.length) {
                selectedFile.value = files.value[0].file;
                await loadEntries();
            } else {
                selectedFile.value = '';
                entries.value = [];
                filtered.value = [];
            }
        }

        async function loadDirectFile() {
            if (!directFilePath.value.trim()) {
                alert('Wpisz ścieżkę do pliku');
                return;
            }
            const path = directFilePath.value.trim();
            selectedFile.value = path;

            try {
                loading.value = true;
                const url = '/api/entries?file=' + encodeURIComponent(path);
                console.log('Loading file from:', url);
                entries.value = await fetchJson(url);
                filtered.value = entries.value;
                applyFilters();
            } catch (e) {
                // Check if error is access denied - try to auto-add parent directory
                if (e.message.includes('Access denied') || e.message.includes('403')) {
                    const parentDir = path.substring(0, path.lastIndexOf('/'));
                    if (parentDir && confirm('Plik nie jest w dozwolonym katalogu. Dodać "' + parentDir + '" do katalogów dozwolonych?')) {
                        allowedDirPath.value = parentDir;
                        await addAllowedDir();
                        // Try loading again after adding directory
                        try {
                            entries.value = await fetchJson('/api/entries?file=' + encodeURIComponent(path));
                            filtered.value = entries.value;
                            applyFilters();
                            return;
                        } catch (e2) {
                            alert('Nadal nie można załadować pliku: ' + e2.message);
                        }
                    }
                } else {
                    alert('Błąd ładowania pliku: ' + e.message);
                }
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
            // Generate readable name from path - use last 2 path segments for clarity
            const pathParts = dir.split('/').filter(Boolean);
            let name = pathParts.slice(-2).join('_') || 'custom_dir';
            // Remove 'allowed_' prefix if present and clean up the name
            name = name.replace(/^allowed_/, '').replace(/_\d+$/, '');
            // If name is still generic, add more context
            if (['var', 'log', 'logs', 'tmp', 'home'].includes(name)) {
                name = pathParts.slice(-3).join('_').replace(/^allowed_/, '');
            }

            try {
                loading.value = true;
                const res = await fetch('/api/config/directories', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name: name, path: dir, type: 'local'})
                });
                const data = await res.json();
                if (data.success) {
                    alert('Katalog dodany: ' + dir);
                    await loadDirectories();
                    // Select the newly added directory and load its files
                    selectedDir.value = name;
                    await loadFiles();
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
                const res = await fetch('/api/config/cleanup-duplicates', {method: 'POST'});
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

        async function cleanupAllowed() {
            try {
                loading.value = true;
                const res = await fetch('/api/config/cleanup-allowed', {method: 'POST'});
                const data = await res.json();
                if (data.success) {
                    alert('Usunięto nazwy allowed_*: ' + data.removed);
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
            selectedFile.value = path;
            await loadEntries();
        }

        async function loadEntries() {
            if (!selectedFile.value) return;
            loading.value = true;
            Object.keys(expanded).forEach(k => delete expanded[k]);
            try {
                entries.value = await fetchJson('/api/entries?file=' + encodeURIComponent(selectedFile.value));
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
                    if (!e.datetime) return true;
                    const d = e.datetime.slice(0, 10);
                    if (dateFrom.value && d < dateFrom.value) return false;
                    if (dateTo.value && d > dateTo.value) return false;
                    return true;
                });
            }
            if (timeFrom.value || timeTo.value) {
                r = r.filter(e => {
                    if (!e.datetime) return true;
                    const t = e.datetime.slice(11, 16);
                    if (timeFrom.value && t < timeFrom.value) return false;
                    if (timeTo.value && t > timeTo.value) return false;
                    return true;
                });
            }
            if (sortOrder.value === 'asc') r = [...r].reverse();
            filtered.value = r;
            tablePage.value = 1; // Reset to first page when filters change
            Object.keys(expanded).forEach(k => delete expanded[k]); // Clear expanded rows
        }

        // DataTable functions
        function toggleTableSort(column) {
            if (tableSortColumn.value === column) {
                tableSortDirection.value = tableSortDirection.value === 'asc' ? 'desc' : 'asc';
            } else {
                tableSortColumn.value = column;
                tableSortDirection.value = 'asc';
            }
            tablePage.value = 1;
        }

        function setTablePage(page) {
            if (page >= 1 && page <= tableTotalPages.value) {
                tablePage.value = page;
                Object.keys(expanded).forEach(k => delete expanded[k]); // Clear expanded rows on page change
            }
        }

        function setTablePageSize(size) {
            tablePageSize.value = size;
            tablePage.value = 1;
        }

        function tablePrevPage() {
            setTablePage(tablePage.value - 1);
        }

        function tableNextPage() {
            setTablePage(tablePage.value + 1);
        }

        function toggleLevel(level) {
            const arr = excludedLevels.value;
            const idx = arr.indexOf(level);
            if (idx >= 0) arr.splice(idx, 1);
            else arr.push(level);
            applyFilters();
        }

        function toggle(i) {
            expanded[i] ? delete expanded[i] : (expanded[i] = true);
        }

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
                const res = await fetch('/api/files' + (selectedDir.value ? '?dir=' + encodeURIComponent(selectedDir.value) : ''));
                const allFiles = await res.json();
                if (!allFiles.some(f => f.file === bm.file)) {
                    alert('Plik już nie istnieje: ' + bm.file.split('/').pop());
                    removeBookmark(bookmarks.value.findIndex(b => b.key === bm.key));
                    return;
                }
            } catch (e) {
            }
            await selectFile(bm.file);
            const idx = filtered.value.findIndex(e => bookmarkKey(e) === bm.key);
            if (idx >= 0) {
                expanded[idx] = true;
                await nextTick();
                const rows = document.querySelectorAll('tbody tr');
                let rowIdx = 0;
                for (let j = 0; j < idx; j++) {
                    rowIdx++;
                    if (expanded[j]) rowIdx++;
                }
                if (rows[rowIdx]) rows[rowIdx].scrollIntoView({block: 'center'});
            }
        }

        async function validateBookmarks() {
            try {
                const res = await fetch('/api/files' + (selectedDir.value ? '?dir=' + encodeURIComponent(selectedDir.value) : ''));
                const allFiles = await res.json();
                const validPaths = new Set(allFiles.map(f => f.file));
                const valid = bookmarks.value.filter(b => validPaths.has(b.file));
                if (valid.length !== bookmarks.value.length) {
                    bookmarks.value = valid;
                    localStorage.setItem('fplv_bookmarks', JSON.stringify(valid));
                }
            } catch (e) {
            }
        }

        async function proceedStep(skip) {
            try {
                const body = {
                    step: currentSetupStep.value,
                    data: {...setupStepData},
                    skip: skip
                };
                const res = await fetch('/api/setup/step', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (data.warning) {
                    setupWarning.value = data.warning;
                } else {
                    setupWarning.value = '';
                }
                if (data.next_step) {
                    currentSetupStep.value = data.next_step;
                    Object.keys(setupStepData).forEach(k => delete setupStepData[k]);
                }
                setupSkipConfirm.value = false;
                if (data.setup_complete) {
                    showSetupWizard.value = false;
                    init();
                }
            } catch (e) {
                alert('Błąd: ' + e.message);
            }
        }

        async function init() {
            try {
                const status = await fetchJson('/api/setup/status');
                if (status.setup_required) {
                    showSetupWizard.value = true;
                    if (status.steps) {
                        setupSteps.value = status.steps;
                    }
                    return;
                }
            } catch (e) {
            }

            try {
                const config = await fetchJson('/api/app-config');
                sshEnabled.value = config.ssh_enabled ?? true;
            } catch (e) {
            }

            try {
                directories.value = await fetchJson('/api/directories');
                syncSSHDirs();
                if (directories.value.length) {
                    selectedDir.value = directories.value[0].key;
                }
            } catch (e) {
            }
            await loadFiles();
            validateBookmarks();
        }

        async function loadDirectories() {
            try {
                directories.value = await fetchJson('/api/directories');
                syncSSHDirs();
                if (directories.value.length) {
                    selectedDir.value = directories.value[0].key;
                }
            } catch (e) {
                console.error('Failed to load directories:', e);
            }
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

                const res = await fetch('/api/ssh/test-connection', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    alert('SSH connection successful!');
                } else {
                    alert('SSH connection failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
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
                port: parseInt(sshForm.port) || 22,
                authMethod: sshForm.authMethod,
                remotePath: sshForm.remotePath || '/var/log',
                keyPath: sshForm.authMethod === 'key' ? sshForm.keyPath : undefined,
                allFiles: sshForm.allFiles || false,
            };

            if (editingIndex.value >= 0) {
                sshConnections.value[editingIndex.value] = conn;
                alert('SSH connection updated!');
            } else {
                sshConnections.value.push(conn);
                alert('SSH connection saved!');
            }

            localStorage.setItem('fplv_ssh_connections', JSON.stringify(sshConnections.value));

            editingIndex.value = -1;
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

        function editSSHConnection(idx) {
            const conn = sshConnections.value[idx];
            Object.assign(sshForm, {
                name: conn.name,
                host: conn.host,
                user: conn.user,
                port: String(conn.port || 22),
                authMethod: conn.authMethod,
                password: '',
                keyPath: conn.keyPath || '',
                keyPassphrase: '',
                remotePath: conn.remotePath || '/var/log',
                allFiles: conn.allFiles || false
            });
            editingIndex.value = idx;
            showSSHModal.value = true;
        }

        function cancelEdit() {
            editingIndex.value = -1;
            Object.assign(sshForm, {
                name: '',
                host: '',
                user: '',
                port: '22',
                authMethod: 'password',
                password: '',
                keyPath: '',
                keyPassphrase: '',
                remotePath: '/var/log',
                allFiles: false
            });
        }

        function connectSSH(idx) {
            const conn = sshConnections.value[idx];
            connectingConnectionIndex.value = idx;
            passwordForConnection.value = '';
            showPasswordModal.value = true;
        }

        async function executeSSHConnection() {
            const idx = connectingConnectionIndex.value;
            const conn = sshConnections.value[idx];
            const password = passwordForConnection.value;
            showPasswordModal.value = false;

            if (conn.authMethod === 'password' && !password) {
                alert('SSH password is required for this connection');
                connectingConnectionIndex.value = -1;
                passwordForConnection.value = '';
                return;
            }

            try {
                const payload = {
                    ssh_host: conn.host,
                    ssh_user: conn.user,
                    ssh_port: parseInt(conn.port) || 22,
                    ssh_auth_method: conn.authMethod,
                    ssh_password: password || undefined,
                    ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                    ssh_key_passphrase: conn.keyPassphrase || undefined,
                    path: conn.remotePath,
                    allFiles: conn.allFiles || false,
                };

                const res = await fetch('/api/ssh/list-files', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    alert(`Found ${data.files.length} log files on ${conn.name}`);
                    data.files.forEach(file => {
                        if (!files.value.some(f => f.file === file.path)) {
                            files.value.push({
                                file: file.path,
                                date: new Date().toISOString().split('T')[0],
                                size: file.size || 0
                            });
                        }
                    });
                } else {
                    alert('Failed to list files: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('SSH connection failed: ' + e.message);
            } finally {
                connectingConnectionIndex.value = -1;
                passwordForConnection.value = '';
            }
        }

        function cancelPasswordModal() {
            showPasswordModal.value = false;
            connectingConnectionIndex.value = -1;
            passwordForConnection.value = '';
        }

        function addManualSSHFile(idx) {
            connectingConnectionIndex.value = idx;
            manualFilePath.value = '';
            showManualFileModal.value = true;
        }

        async function executeManualFileAdd() {
            const idx = connectingConnectionIndex.value;
            const conn = sshConnections.value[idx];
            const password = passwordForConnection.value;
            let filePath = manualFilePath.value;

            if (!filePath) {
                alert('Please enter a file path');
                return;
            }

            if (conn.authMethod === 'password' && !password) {
                alert('SSH password is required for this connection');
                return;
            }

            if (!filePath.startsWith('/')) {
                filePath = '/' + filePath;
            }

            showManualFileModal.value = false;

            try {
                const payload = {
                    ssh_host: conn.host,
                    ssh_user: conn.user,
                    ssh_port: parseInt(conn.port) || 22,
                    ssh_auth_method: conn.authMethod,
                    ssh_password: password || undefined,
                    ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                    ssh_key_passphrase: conn.keyPassphrase || undefined,
                    path: filePath,
                    allFiles: conn.allFiles || false
                };

                const downloadRes = await fetch('/api/ssh/download-file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });

                const downloadData = await downloadRes.json();
                if (!downloadData.success) {
                    alert('Failed to download file: ' + (downloadData.error || 'Unknown error'));
                    return;
                }

                if (!sshFiles.value[conn.name]) {
                    sshFiles.value[conn.name] = [];
                }
                if (!sshFiles.value[conn.name].some(f => f.file === downloadData.localPath)) {
                    sshFiles.value[conn.name].push({
                        file: downloadData.localPath,
                        date: new Date().toISOString().split('T')[0],
                        size: downloadData.size,
                    });
                }

                const sshKey = 'ssh:' + conn.name;
                if (!directories.value.some(d => d.key === sshKey)) {
                    directories.value.push({key: sshKey, path: sshKey, name: 'ssh-' + conn.name});
                }
                selectedDir.value = sshKey;
                files.value = sshFiles.value[conn.name];
                selectedFile.value = downloadData.localPath;
                await loadEntries();

                alert(`File ${filePath} downloaded successfully!\nSaved as: ${downloadData.localPath}\nSize: ${downloadData.size} bytes\nLoaded ${filtered.value.length} log entries`);
            } catch (e) {
                alert('SSH operation failed: ' + e.message);
            } finally {
                connectingConnectionIndex.value = -1;
                manualFilePath.value = '';
            }
        }

        function cancelManualFileModal() {
            showManualFileModal.value = false;
            connectingConnectionIndex.value = -1;
            manualFilePath.value = '';
        }

        async function refreshSSHDir(dirKey) {
            if (!dirKey || !dirKey.startsWith('ssh:')) return;

            const connName = dirKey.replace('ssh:', '');
            const conn = sshConnections.value.find(c => c.name === connName);
            if (!conn) return;

            try {
                loading.value = true;
                // Refresh the SSH files list from memory
                files.value = sshFiles.value[connName] || [];
                if (files.value.length > 0) {
                    selectedFile.value = files.value[0].file;
                    await loadEntries();
                } else {
                    selectedFile.value = '';
                    entries.value = [];
                    filtered.value = [];
                }
            } catch (e) {
                alert('Błąd odświeżania: ' + e.message);
            } finally {
                loading.value = false;
            }
        }

        return {
            files,
            entries,
            filtered,
            selectedFile,
            filterText,
            loading,
            expanded,
            levels,
            levelCounts,
            dateFrom,
            dateTo,
            timeFrom,
            timeTo,
            sortOrder,
            fontSize,
            excludedLevels,
            editorUrl,
            directories,
            selectedDir,
            directFilePath,
            allowedDirPath,
            bookmarks,
            showBookmarks,
            showLevelFilters,
            // DataTable
            tableSortColumn,
            tableSortDirection,
            tablePage,
            tablePageSize,
            TABLE_PAGE_SIZES,
            tableSortedData,
            tableTotalPages,
            tablePaginatedData,
            tableStartRow,
            tableEndRow,
            toggleTableSort,
            setTablePage,
            setTablePageSize,
            tablePrevPage,
            tableNextPage,
            showSetupWizard,
            setupSteps,
            currentSetupStep,
            setupSkipConfirm,
            setupStepData,
            setupWarning,
            sshEnabled,
            showSSHModal,
            showPasswordModal,
            showManualFileModal,
            passwordForConnection,
            manualFilePath,
            sshConnections,
            sshFiles,
            sshForm,
            selectFile,
            loadEntries,
            applyFilters,
            toggle,
            toggleSort,
            toggleLevel,
            syncSSHDirs,
            changeDir,
            formatSize,
            formatDate,
            levelColor,
            levelDot,
            rowBg,
            hasContext,
            openInEditor,
            toggleBookmark,
            isBookmarked,
            removeBookmark,
            goToBookmark,
            testSSHConnection,
            addSSHConnection,
            deleteSSHConnection,
            editSSHConnection,
            cancelEdit,
            connectSSH,
            executeSSHConnection,
            cancelPasswordModal,
            addManualSSHFile,
            executeManualFileAdd,
            cancelManualFileModal,
            loadDirectFile,
            addAllowedDir,
            cleanupDuplicates,
            cleanupAllowed,
            loadDirectories,
            refreshSSHDir,
            proceedStep,
        };
    }
}).mount('#app');
