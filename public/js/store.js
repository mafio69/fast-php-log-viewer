/**
 * Shared reactive store for fast-php-log-viewer
 */
window.FPLV = window.FPLV || {};

(function () {
    const {reactive, computed} = Vue;

    const EDITOR_URL = window.FPLV_CONFIG?.editorUrl || 'phpstorm://open?file={file}&line={line}';

    const store = reactive({
        // File/Dir state
        files: [],
        entries: [],
        filtered: [],
        selectedFile: '',
        selectedDir: '',
        directories: [],
        defaultDirectories: [],
        directFilePath: '',
        directFileMode: 'docker',
        containerId: '',

        // Filter state
        filterText: '',
        excludedLevels: [],
        sortOrder: 'desc',
        dateFrom: '',
        dateTo: '',
        timeFrom: '',
        timeTo: '',
        showLevelFilters: false,

        // UI state
        loading: false,
        expanded: {},
        editorUrl: EDITOR_URL,
        fontSize: parseInt(localStorage.getItem('fplv_fontsize') || '13'),

        // Bookmarks
        bookmarks: JSON.parse(localStorage.getItem('fplv_bookmarks') || '[]'),
        showBookmarks: false,
        MAX_BOOKMARKS: 10,

        // DataTable state
        tableSortColumn: 'datetime',
        tableSortDirection: 'desc',
        tablePage: 1,
        tablePageSize: 100,
        TABLE_PAGE_SIZES: [50, 100, 250, 500, 1000],

        // Setup Wizard state
        showSetupWizard: false,
        setupSteps: [],
        currentSetupStep: '',
        setupSkipConfirm: false,
        setupStepData: {},
        setupWarning: '',
        sshEnabled: true,

        // SSH state
        showSSHModal: false,
        showPasswordModal: false,
        showManualFileModal: false,
        passwordForConnection: '',
        manualFilePath: '',
        connectingConnectionIndex: -1,
        sshConnections: JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]'),
        sshFiles: {},
        editingIndex: -1,
        sshForm: {
            name: '', host: '', user: '', port: '22',
            authMethod: 'password', password: '', keyPath: '', keyPassphrase: '',
            remotePath: '/var/log', allFiles: false,
        },
    });

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

    const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    // Watchers
    Vue.watch(() => store.fontSize, v => localStorage.setItem('fplv_fontsize', String(v)));

    // Computed
    const mergedDirectories = computed(() => {
        const sshItems = store.directories.filter(d => d.key.startsWith('ssh:'));
        const savedItems = store.directories.filter(d => !d.key.startsWith('ssh:'));
        const groups = {
            defaults: {label: 'Domyślne', items: store.defaultDirectories},
            saved: {label: 'Zapisane', items: savedItems},
        };
        if (sshItems.length) {
            groups.ssh = {label: 'SSH', items: sshItems};
        }
        return groups;
    });

    const levelCounts = computed(() => {
        const c = {};
        for (const e of store.entries) c[e.level] = (c[e.level] ?? 0) + 1;
        return c;
    });

    const tableSortedData = computed(() => {
        let data = [...store.filtered];
        const col = store.tableSortColumn;
        const dir = store.tableSortDirection;
        data.sort((a, b) => {
            let valA = a[col] || '';
            let valB = b[col] || '';
            if (col === 'level') {
                const order = {DEBUG: 1, INFO: 2, NOTICE: 3, WARNING: 4, ERROR: 5, CRITICAL: 6, ALERT: 7, EMERGENCY: 8};
                valA = order[valA] || 99;
                valB = order[valB] || 99;
            }
            if (valA < valB) return dir === 'asc' ? -1 : 1;
            if (valA > valB) return dir === 'asc' ? 1 : -1;
            return 0;
        });
        return data;
    });

    const tableTotalPages = computed(() => Math.ceil(tableSortedData.value.length / store.tablePageSize) || 1);

    const tablePaginatedData = computed(() => {
        const start = (store.tablePage - 1) * store.tablePageSize;
        return tableSortedData.value.slice(start, start + store.tablePageSize);
    });

    const tableStartRow = computed(() => store.filtered.length === 0 ? 0 : (store.tablePage - 1) * store.tablePageSize + 1);

    const tableEndRow = computed(() => Math.min(store.tablePage * store.tablePageSize, tableSortedData.value.length));

    // Utility functions
    const levelColor = l => LEVEL_COLORS[l] ?? '#9ca3af';
    const levelDot = l => LEVEL_DOTS[l] ?? '#6b7280';
    const rowBg = l => ROW_BG[l] ?? '';
    const hasContext = e => e.context && Object.keys(e.context).length > 0;

    function openInEditor(location) {
        const [file, line] = location.split(':');
        return store.editorUrl.replace('{file}', encodeURIComponent(file)).replace('{line}', line ?? '1');
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

    function bookmarkKey(entry) {
        return entry.datetime + '|' + entry.message.slice(0, 80);
    }

    function filesApiUrl() {
        const def = store.defaultDirectories.find(d => d.key === store.selectedDir);
        if (def) return '?path=' + encodeURIComponent(def.path);
        if (store.selectedDir) return '?dir=' + encodeURIComponent(store.selectedDir);
        return '';
    }

    async function fetchJson(url) {
        const r = await fetch(url);
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    }

    // ---- Actions ----

    function syncSSHDirs() {
        const conns = JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]');
        for (const conn of conns) {
            const key = 'ssh:' + conn.name;
            if (!store.directories.some(d => d.key === key)) {
                store.directories.push({key, path: key, name: 'ssh-' + conn.name});
            }
        }
    }

    async function loadFiles() {
        if (store.selectedDir && store.selectedDir.startsWith('ssh:')) {
            const connName = store.selectedDir.replace('ssh:', '');
            store.files = store.sshFiles[connName] || [];
            if (store.files.length) {
                store.selectedFile = store.files[0].file;
                await loadEntries();
            } else {
                store.selectedFile = '';
                store.entries = [];
                store.filtered = [];
            }
            return;
        }
        store.files = await fetchJson('/api/files' + filesApiUrl());
        if (store.files.length) {
            store.selectedFile = store.files[0].file;
            await loadEntries();
        } else {
            store.selectedFile = '';
            store.entries = [];
            store.filtered = [];
        }
    }

    async function loadDirectFile() {
        const path = store.directFilePath.trim();
        if (!path) {
            alert('Wpisz ścieżkę do pliku');
            return;
        }
        const containerId = store.containerId.trim();
        let resolvedPath = path;
        if (!containerId && store.directFileMode === 'host') {
            resolvedPath = '/host' + path;
        }
        store.selectedFile = path;
        try {
            store.loading = true;
            let url = '/api/entries?file=' + encodeURIComponent(resolvedPath);
            if (containerId) {
                url += '&container_id=' + encodeURIComponent(containerId);
            }
            store.entries = await fetchJson(url);
            store.filtered = store.entries;
            applyFilters();
        } catch (e) {
            if (!containerId && e.message.includes('access_denied')) {
                const parentDir = resolvedPath.substring(0, resolvedPath.lastIndexOf('/'));
                if (parentDir) {
                    try {
                        await addAllowedDir(parentDir);
                        store.entries = await fetchJson('/api/entries?file=' + encodeURIComponent(resolvedPath));
                        store.filtered = store.entries;
                        applyFilters();
                        return;
                    } catch (e2) {
                        alert('Nie udało się dodać katalogu: ' + e2.message);
                    }
                }
            } else if (e.message.includes('file_not_found')) {
                alert('Plik nie istnieje: ' + path);
            } else if (e.message.includes('container_not_found')) {
                alert('Kontener nie znaleziony: ' + containerId);
            } else if (e.message.includes('docker_unavailable')) {
                alert('Docker niedostępny. Zamontuj /var/run/docker.sock.');
            } else {
                alert('Błąd ładowania pliku: ' + e.message);
            }
            console.error('Load direct file error:', e);
        } finally {
            store.loading = false;
        }
    }

    async function addAllowedDir(dir) {
        if (!dir) {
            alert('Wpisz ścieżkę katalogu');
            return;
        }
        const pathParts = dir.split('/').filter(Boolean);
        let name = pathParts.slice(-2).join('_') || 'custom_dir';
        name = name.replace(/^allowed_/, '').replace(/_\d+$/, '');
        if (['var', 'log', 'logs', 'tmp', 'home'].includes(name)) {
            name = pathParts.slice(-3).join('_').replace(/^allowed_/, '');
        }
        try {
            store.loading = true;
            const res = await fetch('/api/config/directories', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({name, path: dir, type: 'local'})
            });
            const data = await res.json();
            if (data.success) {
                alert('Katalog dodany: ' + dir);
                await loadDirectories();
                const found = store.directories.find(d => d.path === dir);
                store.selectedDir = found ? found.key : name;
                try { await loadFiles(); } catch (e) { /* ignore */ }
            } else {
                alert('Błąd: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            alert('Błąd dodawania katalogu: ' + e.message);
        } finally {
            store.loading = false;
        }
    }

    async function changeDir() {
        store.selectedFile = '';
        store.entries = [];
        store.filtered = [];
        await loadFiles();
    }

    async function selectFile(path) {
        store.selectedFile = path;
        await loadEntries();
    }

    async function loadEntries() {
        if (!store.selectedFile) return;
        store.loading = true;
        Object.keys(store.expanded).forEach(k => delete store.expanded[k]);
        try {
            const def = store.defaultDirectories.find(d => d.key === store.selectedDir);
            const dirParam = def ? def.path : store.selectedDir;
            const url = '/api/entries?file=' + encodeURIComponent(store.selectedFile) + '&dir=' + encodeURIComponent(dirParam);
            store.entries = await fetchJson(url);
            applyFilters();
        } finally {
            store.loading = false;
        }
    }

    function applyFilters() {
        let r = store.entries;
        if (store.excludedLevels.length)
            r = r.filter(e => !store.excludedLevels.includes(e.level));
        if (store.filterText.trim()) {
            const q = store.filterText.toLowerCase();
            r = r.filter(e => e.message.toLowerCase().includes(q) || e.location.toLowerCase().includes(q));
        }
        if (store.dateFrom || store.dateTo) {
            r = r.filter(e => {
                if (!e.datetime) return true;
                const d = e.datetime.slice(0, 10);
                if (store.dateFrom && d < store.dateFrom) return false;
                if (store.dateTo && d > store.dateTo) return false;
                return true;
            });
        }
        if (store.timeFrom || store.timeTo) {
            r = r.filter(e => {
                if (!e.datetime) return true;
                const t = e.datetime.slice(11, 16);
                if (store.timeFrom && t < store.timeFrom) return false;
                if (store.timeTo && t > store.timeTo) return false;
                return true;
            });
        }
        if (store.sortOrder === 'asc') r = [...r].reverse();
        store.filtered = r;
        store.tablePage = 1;
        Object.keys(store.expanded).forEach(k => delete store.expanded[k]);
    }

    function toggle(entryIndex) {
        store.expanded[entryIndex] ? delete store.expanded[entryIndex] : (store.expanded[entryIndex] = true);
    }

    function toggleSort() {
        store.sortOrder = store.sortOrder === 'desc' ? 'asc' : 'desc';
        applyFilters();
    }

    function isBookmarked(entry) {
        const key = bookmarkKey(entry);
        return store.bookmarks.some(b => b.key === key);
    }

    function toggleBookmark(entry) {
        const key = bookmarkKey(entry);
        const idx = store.bookmarks.findIndex(b => b.key === key);
        if (idx >= 0) {
            store.bookmarks.splice(idx, 1);
        } else {
            if (store.bookmarks.length >= store.MAX_BOOKMARKS) store.bookmarks.shift();
            store.bookmarks.push({
                key, file: store.selectedFile, datetime: entry.datetime,
                level: entry.level, message: entry.message.slice(0, 120), location: entry.location,
            });
        }
        localStorage.setItem('fplv_bookmarks', JSON.stringify(store.bookmarks));
    }

    function removeBookmark(idx) {
        store.bookmarks.splice(idx, 1);
        localStorage.setItem('fplv_bookmarks', JSON.stringify(store.bookmarks));
    }

    async function goToBookmark(bm) {
        store.showBookmarks = false;
        try {
            const res = await fetch('/api/files' + filesApiUrl());
            const allFiles = await res.json();
            if (!allFiles.some(f => f.file === bm.file)) {
                alert('Plik już nie istnieje: ' + bm.file.split('/').pop());
                removeBookmark(store.bookmarks.findIndex(b => b.key === bm.key));
                return;
            }
        } catch (e) {
        }
        await selectFile(bm.file);
        const idx = store.filtered.findIndex(e => bookmarkKey(e) === bm.key);
        if (idx >= 0) {
            store.expanded[idx] = true;
            await Vue.nextTick();
            const rows = document.querySelectorAll('tbody tr');
            let rowIdx = 0;
            for (let j = 0; j < idx; j++) {
                rowIdx++;
                if (store.expanded[j]) rowIdx++;
            }
            if (rows[rowIdx]) rows[rowIdx].scrollIntoView({block: 'center'});
        }
    }

    async function validateBookmarks() {
        try {
            const res = await fetch('/api/files' + filesApiUrl());
            const allFiles = await res.json();
            const validPaths = new Set(allFiles.map(f => f.file));
            const valid = store.bookmarks.filter(b => validPaths.has(b.file));
            if (valid.length !== store.bookmarks.length) {
                store.bookmarks = valid;
                localStorage.setItem('fplv_bookmarks', JSON.stringify(valid));
            }
        } catch (e) {
        }
    }

    async function proceedStep(skip) {
        const step = store.currentSetupStep;
        if (!skip && step === 'ssh_config') {
            if (!store.setupStepData.ssh_host || !store.setupStepData.ssh_user) {
                alert('Wypełnij pole Host i Użytkownik SSH lub kliknij "Pomiń".');
                return;
            }
        }
        let stepData = {...store.setupStepData};
        if (step === 'local_directories' && !skip && stepData.path) {
            stepData = {directories: [{path: stepData.path, name: stepData.name || 'Local Directory'}]};
        }
        store.setupWarning = '';
        try {
            const body = {step, data: stepData, skip};
            const res = await fetch('/api/setup/step', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.error) {
                if (data.fields) {
                    alert('Brakujące pola: ' + data.fields.join(', '));
                } else {
                    alert('Błąd: ' + (data.error || 'Nieznany błąd'));
                }
                return;
            }
            if (data.warning) {
                store.setupWarning = data.warning;
            } else {
                store.setupWarning = '';
            }
            if (data.next_step) {
                store.currentSetupStep = data.next_step;
                Object.keys(store.setupStepData).forEach(k => delete store.setupStepData[k]);
            }
            store.setupSkipConfirm = false;
            if (data.setup_complete) {
                store.showSetupWizard = false;
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
                store.showSetupWizard = true;
                store.setupSkipConfirm = false;
                Object.keys(store.setupStepData).forEach(k => delete store.setupStepData[k]);
                if (status.steps && status.steps.length > 0) {
                    store.setupSteps = status.steps;
                    store.currentSetupStep = status.steps[0].name;
                } else {
                    store.currentSetupStep = 'generate_keys';
                }
                return;
            }
        } catch (e) {
        }
        try {
            const config = await fetchJson('/api/app-config');
            store.sshEnabled = config.ssh_enabled ?? true;
            if (config.ssh_profiles && config.ssh_profiles.length) {
                const profiles = config.ssh_profiles.map(p => ({
                    name: p.name, host: p.ssh_host, user: p.ssh_user, port: p.ssh_port || 22,
                    authMethod: p.ssh_auth_method || 'password', keyPath: p.ssh_key_path || '',
                    remotePath: p.remote_path || '/var/log', allFiles: p.all_files || false,
                }));
                localStorage.setItem('fplv_ssh_connections', JSON.stringify(profiles));
                store.sshConnections = profiles;
            }
        } catch (e) {
        }
        await loadDefaultDirectories();
        await loadDirectories();
        await loadFiles();
        validateBookmarks();
    }

    async function loadDefaultDirectories() {
        try {
            store.defaultDirectories = await fetchJson('/api/config/default-directories');
        } catch (e) {
            console.error('Failed to load default directories:', e);
            store.defaultDirectories = [];
        }
    }

    async function loadDirectories() {
        try {
            store.directories = await fetchJson('/api/directories');
            syncSSHDirs();
            const firstDefault = store.defaultDirectories[0];
            store.selectedDir = firstDefault ? firstDefault.key : '';
        } catch (e) {
            console.error('Failed to load directories:', e);
        }
    }

    // DataTable functions
    function toggleTableSort(column) {
        if (store.tableSortColumn === column) {
            store.tableSortDirection = store.tableSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            store.tableSortColumn = column;
            store.tableSortDirection = 'asc';
        }
        store.tablePage = 1;
    }

    function setTablePage(page) {
        if (page >= 1 && page <= tableTotalPages.value) {
            store.tablePage = page;
            Object.keys(store.expanded).forEach(k => delete store.expanded[k]);
        }
    }

    function setTablePageSize(size) {
        store.tablePageSize = size;
        store.tablePage = 1;
    }

    function tablePrevPage() {
        setTablePage(store.tablePage - 1);
    }

    function tableNextPage() {
        setTablePage(store.tablePage + 1);
    }

    function toggleLevel(level) {
        const arr = store.excludedLevels;
        const idx = arr.indexOf(level);
        if (idx >= 0) arr.splice(idx, 1); else arr.push(level);
        applyFilters();
    }

    // SSH functions
    async function testSSHConnection() {
        try {
            const conn = store.sshForm;
            const payload = {
                ssh_host: conn.host, ssh_user: conn.user, ssh_port: parseInt(conn.port) || 22,
                ssh_auth_method: conn.authMethod,
                ssh_password: conn.authMethod === 'password' ? conn.password : undefined,
                ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                ssh_key_passphrase: conn.authMethod === 'key' ? conn.keyPassphrase : undefined,
            };
            const res = await fetch('/api/ssh/test-connection', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
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
        const form = store.sshForm;
        if (!form.name || !form.host || !form.user) {
            alert('Please fill in name, host, and user');
            return;
        }
        const conn = {
            name: form.name, host: form.host, user: form.user, port: parseInt(form.port) || 22,
            authMethod: form.authMethod, remotePath: form.remotePath || '/var/log',
            keyPath: form.authMethod === 'key' ? form.keyPath : undefined, allFiles: form.allFiles || false,
        };
        if (store.editingIndex >= 0) {
            store.sshConnections[store.editingIndex] = conn;
            alert('SSH connection updated!');
        } else {
            store.sshConnections.push(conn);
            alert('SSH connection saved!');
        }
        localStorage.setItem('fplv_ssh_connections', JSON.stringify(store.sshConnections));
        store.editingIndex = -1;
        Object.assign(store.sshForm, {
            name: '', host: '', user: '', port: '22', authMethod: 'password', password: '', keyPath: '',
            keyPassphrase: '', remotePath: '/var/log', allFiles: false
        });
    }

    function deleteSSHConnection(idx) {
        if (confirm('Delete this SSH connection?')) {
            store.sshConnections.splice(idx, 1);
            localStorage.setItem('fplv_ssh_connections', JSON.stringify(store.sshConnections));
        }
    }

    function editSSHConnection(idx) {
        const conn = store.sshConnections[idx];
        Object.assign(store.sshForm, {
            name: conn.name, host: conn.host, user: conn.user, port: String(conn.port || 22),
            authMethod: conn.authMethod, password: '', keyPath: conn.keyPath || '', keyPassphrase: '',
            remotePath: conn.remotePath || '/var/log', allFiles: conn.allFiles || false
        });
        store.editingIndex = idx;
        store.showSSHModal = true;
    }

    function cancelEdit() {
        store.editingIndex = -1;
        Object.assign(store.sshForm, {
            name: '', host: '', user: '', port: '22', authMethod: 'password', password: '', keyPath: '',
            keyPassphrase: '', remotePath: '/var/log', allFiles: false
        });
    }

    function connectSSH(idx) {
        store.connectingConnectionIndex = idx;
        store.passwordForConnection = '';
        store.showPasswordModal = true;
    }

    async function executeSSHConnection() {
        const idx = store.connectingConnectionIndex;
        const conn = store.sshConnections[idx];
        const password = store.passwordForConnection;
        store.showPasswordModal = false;
        if (conn.authMethod === 'password' && !password) {
            alert('SSH password is required for this connection');
            store.connectingConnectionIndex = -1;
            store.passwordForConnection = '';
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
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                alert(`Found ${data.files.length} log files on ${conn.name}`);
                data.files.forEach(file => {
                    if (!store.files.some(f => f.file === file.path)) {
                        store.files.push({
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
            store.connectingConnectionIndex = -1;
            store.passwordForConnection = '';
        }
    }

    function cancelPasswordModal() {
        store.showPasswordModal = false;
        store.connectingConnectionIndex = -1;
        store.passwordForConnection = '';
    }

    function addManualSSHFile(idx) {
        store.connectingConnectionIndex = idx;
        store.manualFilePath = '';
        store.showManualFileModal = true;
    }

    async function executeManualFileAdd() {
        const idx = store.connectingConnectionIndex;
        const conn = store.sshConnections[idx];
        const password = store.passwordForConnection;
        let filePath = store.manualFilePath;
        if (!filePath) {
            alert('Please enter a file path');
            return;
        }
        if (conn.authMethod === 'password' && !password) {
            alert('SSH password is required for this connection');
            return;
        }
        if (!filePath.startsWith('/')) filePath = '/' + filePath;
        store.showManualFileModal = false;
        try {
            const payload = {
                ssh_host: conn.host, ssh_user: conn.user, ssh_port: parseInt(conn.port) || 22,
                ssh_auth_method: conn.authMethod, ssh_password: password || undefined,
                ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                ssh_key_passphrase: conn.keyPassphrase || undefined, path: filePath, allFiles: conn.allFiles || false,
            };
            const downloadRes = await fetch('/api/ssh/download-file', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
            });
            const downloadData = await downloadRes.json();
            if (!downloadData.success) {
                alert('Failed to download file: ' + (downloadData.error || 'Unknown error'));
                return;
            }
            if (!store.sshFiles[conn.name]) store.sshFiles[conn.name] = [];
            if (!store.sshFiles[conn.name].some(f => f.file === downloadData.localPath)) {
                store.sshFiles[conn.name].push({
                    file: downloadData.localPath,
                    date: new Date().toISOString().split('T')[0],
                    size: downloadData.size
                });
            }
            const sshKey = 'ssh:' + conn.name;
            if (!store.directories.some(d => d.key === sshKey)) {
                store.directories.push({key: sshKey, path: sshKey, name: 'ssh-' + conn.name});
            }
            store.selectedDir = sshKey;
            store.files = store.sshFiles[conn.name];
            store.selectedFile = downloadData.localPath;
            await loadEntries();
            alert(`File ${filePath} downloaded successfully!\nSaved as: ${downloadData.localPath}\nSize: ${downloadData.size} bytes\nLoaded ${store.filtered.length} log entries`);
        } catch (e) {
            alert('SSH operation failed: ' + e.message);
        } finally {
            store.connectingConnectionIndex = -1;
            store.manualFilePath = '';
        }
    }

    function cancelManualFileModal() {
        store.showManualFileModal = false;
        store.connectingConnectionIndex = -1;
        store.manualFilePath = '';
    }

    async function refreshSSHDir(dirKey) {
        if (!dirKey || !dirKey.startsWith('ssh:')) return;
        const connName = dirKey.replace('ssh:', '');
        const conn = store.sshConnections.find(c => c.name === connName);
        if (!conn) return;
        try {
            store.loading = true;
            store.files = store.sshFiles[connName] || [];
            if (store.files.length > 0) {
                store.selectedFile = store.files[0].file;
                await loadEntries();
            } else {
                store.selectedFile = '';
                store.entries = [];
                store.filtered = [];
            }
        } catch (e) {
            alert('Błąd odświeżania: ' + e.message);
        } finally {
            store.loading = false;
        }
    }

    // Expose store and functions
    Object.assign(window.FPLV, {
        store,
        mergedDirectories, levelCounts, tableSortedData, tableTotalPages, tablePaginatedData,
        tableStartRow, tableEndRow, LEVELS, levelColor, levelDot, rowBg, hasContext,
        openInEditor, formatSize, formatDate, bookmarkKey, fetchJson,
        init, loadFiles, loadDirectFile, addAllowedDir, changeDir, selectFile, loadEntries,
        loadDefaultDirectories, loadDirectories, syncSSHDirs, refreshSSHDir,
        applyFilters, toggle, toggleSort, toggleLevel, isBookmarked, toggleBookmark,
        removeBookmark, goToBookmark, validateBookmarks, filesApiUrl,
        toggleTableSort, setTablePage, setTablePageSize, tablePrevPage, tableNextPage,
        testSSHConnection, addSSHConnection, deleteSSHConnection, editSSHConnection,
        cancelEdit, connectSSH, executeSSHConnection, cancelPasswordModal,
        addManualSSHFile, executeManualFileAdd, cancelManualFileModal, proceedStep,
    });
})();
