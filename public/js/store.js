/**
 * Shared reactive store for fast-php-log-viewer
 */
window.FPLV = window.FPLV || {};

(function () {
    const F = window.FPLV;
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
        activeSshConnection: null, // null or {name, host, user, port}
        containerId: '',
        containerCheckStatus: '', // '' | 'checking' | 'ok' | 'not_found' | 'not_allowed' | 'path_not_allowed' | 'error'
        selectedFileContainerId: '', // container_id the currently selected/listed files came from, '' for local/host

        // Directory/container management panel (Zapisane / Odłożone / Config)
        showDirManager: false,
        dirManagerTab: 'saved', // 'saved' | 'deferred' | 'config'
        deferredDirectories: [],
        allowedContainersList: [],
        allowedPathsList: [],

        // Filter state
        filterText: '',
        excludedLevels: [],
        dateFrom: '',
        dateTo: '',
        timeFrom: '00:00',
        timeTo: '23:59',
        showLevelFilters: false,

        // UI state
        loading: false,
        expanded: {},
        editorUrl: EDITOR_URL,
        fontSize: parseInt(localStorage.getItem('fplv_fontsize') || '16'),

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
        setupKeyDisplay: '',
        setupPresets: ['/var/log/nginx', '/var/log/apache2', '/var/log/php-fpm'],
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
        // In-memory only (never persisted): { [connName]: {host,user,port,authMethod,password?,keyPath?,keyPassphrase?} },
        // cached after a successful connect/read so re-opening a file from the same
        // connection later in the session doesn't need the password again.
        sshActiveCredentials: {},
        // What submitting the (shared) password modal should do: 'connect' opens a
        // directory listing (executeSSHConnection), 'read' fetches one file's
        // entries (performPendingSshRead) - set alongside pendingSshRead.
        passwordModalPurpose: 'connect',
        pendingSshRead: null, // {connName, path} while passwordModalPurpose === 'read'
        sshStatusMessage: '', // inline, non-blocking feedback for the SSH panel (test/save/connect)
        sshStatusType: 'success', // 'success' | 'error'
        editingIndex: -1,
        sshForm: {...window.FPLV_CONFIG?.sshFormDefaults || {
            name: '', host: '', user: '', port: '22',
            authMethod: 'password', password: '', keyPath: '', keyPassphrase: '',
            remotePath: '/var/log', allFiles: false,
        }},
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
    Vue.watch(() => [store.containerId, store.directFilePath, store.directFileMode], () => F.scheduleContainerCheck());

    // Computed
    const mergedDirectories = computed(() => {
        const groups = {
            defaults: {label: 'Domyślne', items: store.defaultDirectories},
            saved: {label: 'Zapisane', items: store.directories},
        };
        // Vue 3 evaluates v-if before v-for on the same element, so an empty
        // group can't be skipped in the template (group would be undefined
        // there) - filtering here is the option that actually works.
        for (const key of Object.keys(groups)) {
            if (!groups[key].items.length) delete groups[key];
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
    function clearExpanded() {
        Object.keys(store.expanded).forEach(k => delete store.expanded[k]);
    }

    function clearSetupStepData() {
        Object.keys(store.setupStepData).forEach(k => delete store.setupStepData[k]);
    }



    /**
     * Non-blocking replacement for alert() in the SSH connection flow (test/save/
     * connect). Native alert()/confirm() block the whole page's JS thread until
     * dismissed, which made this panel confusing to use (unclear what happened
     * behind the dialog) and broke browser-automation testing outright (CDP
     * commands time out while a dialog is open). Success messages auto-clear;
     * errors stay until the next action replaces them.
     */

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

    function formatDirLabel(key, max = 40) {
        if (!key || key.length <= max) return key;
        const head = Math.ceil((max - 1) * 0.4);
        const tail = (max - 1) - head;
        return key.slice(0, head) + '…' + key.slice(key.length - tail);
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

    // A "Zapisane" entry of type docker needs container_id+path, not the dir=
    // lookup that works for local/ssh saved entries - findSavedDockerDir() is
    // the single place that decides this, shared by filesApiUrl() and loadFiles()
    // so bookmark validation (which also calls filesApiUrl()) gets it right too.
    function findSavedDockerDir() {
        const saved = store.directories.find(d => d.key === store.selectedDir);
        return (saved && saved.type === 'docker' && saved.container_id) ? saved : null;
    }

    function filesApiUrl() {
        const def = store.defaultDirectories.find(d => d.key === store.selectedDir);
        if (def) return '?path=' + encodeURIComponent(def.path);
        const dockerDir = findSavedDockerDir();
        if (dockerDir) {
            return '?container_id=' + encodeURIComponent(dockerDir.container_id) + '&path=' + encodeURIComponent(dockerDir.path);
        }
        if (store.selectedDir) return '?dir=' + encodeURIComponent(store.selectedDir);
        return '';
    }

    async function fetchJson(url) {
        const r = await fetch(url);
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    }

    // ---- Actions ----


    async function loadFiles() {
        if (store.selectedDir && store.selectedDir.startsWith('ssh:')) {
            const connName = store.selectedDir.replace('ssh:', '');
            const cached = store.sshFiles[connName];
            store.selectedFileContainerId = '';
            if (cached && cached.length) {
                store.files = cached;
                store.selectedFile = cached[0].file;
                await loadEntries();
            } else {
                // Nothing fetched yet this session (fresh page load, or this ssh:
                // entry only exists because a saved profile was synced into
                // store.directories) - prompt to connect instead of showing an
                // empty list with no way forward.
                store.files = [];
                store.selectedFile = '';
                store.entries = [];
                store.filtered = [];
                const idx = store.sshConnections.findIndex(c => c.name === connName);
                if (idx >= 0) connectSSH(idx);
            }
            return;
        }
        const dockerDir = findSavedDockerDir();
        store.selectedFileContainerId = dockerDir ? dockerDir.container_id : '';
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
            alert('Wpisz ścieżkę do ' + (store.directFileMode === 'docker' ? 'katalogu' : 'pliku'));
            return;
        }

        if (store.activeSshConnection) {
            await F.loadSshFileEntries(store.activeSshConnection.name, path);
            return;
        }

        if (store.directFileMode === 'docker') {
            await F.loadDirectDockerFiles(path);
            return;
        }

        await loadDirectHostFile(path);
    }

    async function loadDirectHostFile(path) {
        const resolvedPath = '/host' + path;
        store.selectedFile = path;
        try {
            store.loading = true;
            store.entries = await fetchJson('/api/entries?file=' + encodeURIComponent(resolvedPath));
            store.filtered = store.entries;
            applyFilters();
        } catch (e) {
            if (e.message.includes('access_denied')) {
                const parentDir = resolvedPath.substring(0, resolvedPath.lastIndexOf('/'));
                if (parentDir) {
                    try {
                        await addAllowedDir(parentDir);
                        store.entries = await fetchJson('/api/entries?file=' + encodeURIComponent(resolvedPath));
                        store.filtered = store.entries;
                        applyFilters();
                        return;
                    } catch (e2) {
                        alert('Nie udało się dodać katalogu.');
                        console.error(e2);
                        return;
                    }
                }
            }
            if (e.message.includes('file_not_found')) {
                alert('Plik nie istnieje.');
                console.error('File not found:', path);
            } else {
                alert('Nie udało się załadować pliku.'); console.error(e);
            }
            console.error('Load direct file error:', e);
        } finally {
            store.loading = false;
        }
    }


    // ---- Directory/container management panel ----

    async function openDirManager() {
        store.showDirManager = true;
        store.dirManagerTab = 'saved';
        await Promise.all([loadDeferredDirectories(), loadAllowedContainersList(), loadAllowedPathsList()]);
    }

    async function loadDeferredDirectories() {
        try {
            store.deferredDirectories = await fetchJson('/api/config/directories/deferred');
        } catch (e) {
            console.error('Failed to load deferred directories:', e);
        }
    }

    async function loadAllowedContainersList() {
        try {
            store.allowedContainersList = await fetchJson('/api/config/allowed-containers');
        } catch (e) {
            console.error('Failed to load allowed containers:', e);
        }
    }

    async function loadAllowedPathsList() {
        try {
            store.allowedPathsList = await fetchJson('/api/config/allowed-container-paths');
        } catch (e) {
            console.error('Failed to load allowed container paths:', e);
        }
    }

    async function deleteDirectoryEntry(id) {
        await fetch('/api/config/directories/' + id, {method: 'DELETE'});
        await Promise.all([loadDirectories(), loadDeferredDirectories()]);
    }

    async function deferDirectoryEntry(id) {
        await fetch('/api/config/directories/' + id, {
            method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({is_active: 0})
        });
        await Promise.all([loadDirectories(), loadDeferredDirectories()]);
    }

    async function restoreDirectoryEntry(id) {
        await fetch('/api/config/directories/' + id, {
            method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({is_active: 1})
        });
        await Promise.all([loadDirectories(), loadDeferredDirectories()]);
    }

    async function deleteAllowedContainerEntry(id) {
        await fetch('/api/config/allowed-containers/' + id, {method: 'DELETE'});
        await loadAllowedContainersList();
    }

    async function deleteAllowedPathEntry(id) {
        await fetch('/api/config/allowed-container-paths/' + id, {method: 'DELETE'});
        await loadAllowedPathsList();
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
                alert('Wystąpił błąd.'); console.error(data);
            }
        } catch (e) {
            alert('Nie udało się dodać katalogu.'); console.error(e);
        } finally {
            store.loading = false;
        }
    }

    async function changeDir() {
        store.selectedFile = '';
        store.entries = [];
        store.filtered = [];
        store.selectedFileContainerId = '';
        await loadFiles();
    }

    async function selectFile(path) {
        store.selectedFile = path;
        await loadEntries();
    }

    async function loadEntries() {
        if (!store.selectedFile) return;
        if (store.selectedDir && store.selectedDir.startsWith('ssh:')) {
            const connName = store.selectedDir.replace('ssh:', '');
            const fileEntry = (store.sshFiles[connName] || []).find(f => f.file === store.selectedFile);
            // "Download File" saves a LOCAL copy first (fileEntry.sshLocal) and its
            // path should still go through the plain local-file read below (which
            // FileAccessValidator's ssh: dirKey branch already handles via
            // realpath()) - only entries from live directory listings (no local
            // copy) are remote paths that need fetching over SSH on demand.
            if (!fileEntry || !fileEntry.sshLocal) {
                await F.loadSshFileEntries(connName, store.selectedFile);
                return;
            }
        }
        store.loading = true;
        clearExpanded();
        try {
            let url;
            if (store.selectedFileContainerId) {
                url = '/api/entries?file=' + encodeURIComponent(store.selectedFile)
                    + '&container_id=' + encodeURIComponent(store.selectedFileContainerId);
            } else {
                const def = store.defaultDirectories.find(d => d.key === store.selectedDir);
                const dirParam = def ? def.path : store.selectedDir;
                url = '/api/entries?file=' + encodeURIComponent(store.selectedFile) + '&dir=' + encodeURIComponent(dirParam);
            }
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
                if (!e.datetime) return false;
                const d = e.datetime.slice(0, 10);
                if (store.dateFrom && d < store.dateFrom) return false;
                if (store.dateTo && d > store.dateTo) return false;
                return true;
            });
        }
        const hasTimeFilter =
            (store.timeFrom && store.timeFrom !== '00:00') ||
            (store.timeTo && !['23:59', '23:59:59'].includes(store.timeTo));
        if (hasTimeFilter) {
            r = r.filter(e => {
                if (!e.datetime) return false;
                const t = e.datetime.slice(11, 19);
                if (store.timeFrom && t < (store.timeFrom.length === 5 ? store.timeFrom + ':00' : store.timeFrom)) return false;
                if (store.timeTo && t > (store.timeTo.length === 5 ? store.timeTo + ':59' : store.timeTo)) return false;
                return true;
            });
        }
        store.filtered = r;
        store.tablePage = 1;
        clearExpanded();
    }

    function toggle(entryIndex) {
        store.expanded[entryIndex] ? delete store.expanded[entryIndex] : (store.expanded[entryIndex] = true);
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
                alert('Plik już nie istnieje.');
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

        // If encryption key is already displayed, user clicked "Dalej" to advance
        if (step === 'generate_keys' && store.setupKeyDisplay) {
            store.setupKeyDisplay = '';
            store.currentSetupStep = store.setupSteps.length > 1
                ? store.setupSteps[1].name
                : 'ssh_config';
            clearSetupStepData();
            return;
        }

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
                    alert('Wystąpił błąd konfiguracji.'); console.error(data);
                }
                return;
            }
            if (data.warning) {
                store.setupWarning = data.warning;
            } else {
                store.setupWarning = '';
            }

            // Show encryption key before advancing
            if (step === 'generate_keys' && data.encryption_key_display) {
                store.setupKeyDisplay = data.encryption_key_display;
                return;
            }

            if (data.next_step) {
                store.currentSetupStep = data.next_step;
                clearSetupStepData();
            }
            store.setupSkipConfirm = false;
            if (data.setup_complete) {
                store.showSetupWizard = false;
                init();
            }
        } catch (e) {
            alert('Wystąpił błąd.'); console.error(e);
        }
    }

    async function init() {
        try {
            const status = await fetchJson('/api/setup/status');
            if (status.setup_required) {
                store.showSetupWizard = true;
                store.setupSkipConfirm = false;
                store.setupKeyDisplay = '';
                clearSetupStepData();
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
            F.syncSSHDirs();
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
            clearExpanded();
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

    // Expose store and functions
    Object.assign(window.FPLV, {
        store,
        mergedDirectories, levelCounts, tableSortedData, tableTotalPages, tablePaginatedData,
        tableStartRow, tableEndRow, LEVELS, levelColor, levelDot, rowBg, hasContext,
        openInEditor, formatSize, formatDate, formatDirLabel, bookmarkKey, fetchJson,
        init, loadFiles, loadDirectFile, addAllowedDir, changeDir, selectFile, loadEntries,
        loadDefaultDirectories, loadDirectories,
        applyFilters, toggle, toggleLevel, isBookmarked, toggleBookmark,
        removeBookmark, goToBookmark, validateBookmarks,
        toggleTableSort, setTablePage, setTablePageSize, tablePrevPage, tableNextPage,
        proceedStep, openDirManager, deleteDirectoryEntry, deferDirectoryEntry,
        restoreDirectoryEntry, deleteAllowedContainerEntry, deleteAllowedPathEntry,
    });
})();
