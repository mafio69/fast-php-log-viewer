/**
 * store-ssh.js — SSH connection management, file listing & reading
 */
window.FPLV = window.FPLV || {};

(function () {
    const F = window.FPLV;
    const store = F.store;

    let sshStatusTimer = null;

    function setSshStatus(type, message) {
        clearTimeout(sshStatusTimer);
        store.sshStatusType = type;
        store.sshStatusMessage = message;
        if (type === 'success') {
            sshStatusTimer = setTimeout(() => {
                if (store.sshStatusMessage === message) store.sshStatusMessage = '';
            }, 4000);
        }
    }

    function resetConnectionState() {
        store.connectingConnectionIndex = -1;
        store.passwordForConnection = '';
        store.manualFilePath = '';
    }

    const SSH_FORM_DEFAULTS = {
        name: '', host: '', user: '', port: '22',
        authMethod: 'password', password: '', keyPath: '',
        keyPassphrase: '', remotePath: '/var/log', allFiles: false,
    };

    async function testSSHConnection() {
        const conn = store.sshForm;
        if (!conn.host || !conn.user) {
            F.setSshStatus('error', 'Please fill in host and user');
            return;
        }
        if (conn.authMethod === 'password') {
            store.passwordForConnection = '';
            store.passwordModalPurpose = 'test';
            store.showPasswordModal = true;
            return;
        }
        await doTestSSHConnection();
    }

    async function doTestSSHConnection() {
        try {
            const conn = store.sshForm;
            const payload = {
                ssh_host: conn.host, ssh_user: conn.user, ssh_port: parseInt(conn.port) || 22,
                ssh_auth_method: conn.authMethod,
                ssh_password: conn.authMethod === 'password' ? store.passwordForConnection : undefined,
                ssh_key_path: conn.authMethod === 'key' ? conn.keyPath : undefined,
                ssh_key_passphrase: conn.authMethod === 'key' ? conn.keyPassphrase : undefined,
            };
            const res = await fetch('/api/ssh/test-connection', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                F.setSshStatus('success', 'SSH connection successful!');
            } else {
                F.setSshStatus('error', 'SSH connection failed: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            F.setSshStatus('error', 'SSH connection failed: ' + e.message);
        } finally {
            store.passwordForConnection = '';
        }
    }

    function addSSHConnection() {
        const form = store.sshForm;
        if (!form.name || !form.host || !form.user) {
            F.setSshStatus('error', 'Please fill in name, host, and user');
            return;
        }
        const conn = {
            name: form.name, host: form.host, user: form.user, port: parseInt(form.port) || 22,
            authMethod: form.authMethod, remotePath: form.remotePath || '/var/log',
            keyPath: form.authMethod === 'key' ? form.keyPath : undefined, allFiles: form.allFiles || false,
        };
        if (store.editingIndex >= 0) {
            store.sshConnections[store.editingIndex] = conn;
            F.setSshStatus('success', 'SSH connection updated!');
        } else {
            store.sshConnections.push(conn);
            F.setSshStatus('success', 'SSH connection saved!');
        }
        localStorage.setItem('fplv_ssh_connections', JSON.stringify(store.sshConnections));
        store.editingIndex = -1;
        Object.assign(store.sshForm, SSH_FORM_DEFAULTS);
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
        Object.assign(store.sshForm, SSH_FORM_DEFAULTS);
    }

    function openSSHModal() {
        store.showSSHModal = true;
        store.sshStatusMessage = '';
    }

    function connectSSH(idx) {
        store.showSSHModal = true;
        store.connectingConnectionIndex = idx;
        store.passwordForConnection = '';
        store.passwordModalPurpose = 'connect';
        store.pendingSshRead = null;
        store.showPasswordModal = true;
    }

    function credsFromConn(conn, password) {
        return {
            host: conn.host, user: conn.user, port: parseInt(conn.port) || 22,
            authMethod: conn.authMethod,
            password: conn.authMethod === 'password' ? password : undefined,
            keyPath: conn.authMethod === 'key' ? conn.keyPath : undefined,
            keyPassphrase: conn.authMethod === 'key' ? conn.keyPassphrase : undefined,
        };
    }

    function sshRequestPayload(creds, extra) {
        return {
            ssh_host: creds.host, ssh_user: creds.user, ssh_port: creds.port,
            ssh_auth_method: creds.authMethod,
            ssh_password: creds.authMethod === 'password' ? creds.password : undefined,
            ssh_key_path: creds.authMethod === 'key' ? creds.keyPath : undefined,
            ssh_key_passphrase: creds.authMethod === 'key' ? creds.keyPassphrase : undefined,
            ...extra,
        };
    }

    async function performSshListing(creds, conn) {
        const res = await fetch('/api/ssh/list-files', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(sshRequestPayload(creds, {path: conn.remotePath, allFiles: conn.allFiles || false}))
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'unknown');
        return data.files.map(f => ({file: f.path, date: new Date().toISOString().split('T')[0], size: f.size || 0}));
    }

    async function fetchSshEntries(creds, path) {
        const res = await fetch('/api/ssh/read-file', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(sshRequestPayload(creds, {path}))
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'unknown');
        return data.entries;
    }

    async function connectAndList(idx, password) {
        const conn = store.sshConnections[idx];
        const creds = credsFromConn(conn, password);
        store.loading = true;
        try {
            const files = await performSshListing(creds, conn);
            store.sshActiveCredentials[conn.name] = creds;
            store.activeSshConnection = {name: conn.name, host: conn.host, user: conn.user, port: conn.port};
            store.sshFiles[conn.name] = files;
            store.selectedDir = 'ssh:' + conn.name;
            store.selectedFileContainerId = '';
            store.files = files;
            if (files.length) {
                store.selectedFile = files[0].file;
                await loadSshFileEntries(conn.name, store.selectedFile);
            } else {
                store.selectedFile = '';
                store.entries = [];
                store.filtered = [];
            }
            store.showSSHModal = false;
        } catch (e) {
            F.setSshStatus('error', 'SSH connection failed: ' + e.message);
        } finally {
            store.loading = false;
        }
    }

    async function executeSSHConnection() {
        const idx = store.connectingConnectionIndex;
        const conn = store.sshConnections[idx];
        const password = store.passwordForConnection;
        store.showPasswordModal = false;
        if (conn.authMethod === 'password' && !password) {
            F.setSshStatus('error', 'SSH password is required for this connection');
            F.resetConnectionState();
            return;
        }
        await connectAndList(idx, password);
        F.resetConnectionState();
    }

    async function loadSshFileEntries(connName, path) {
        const conn = store.sshConnections.find(c => c.name === connName);
        const cached = store.sshActiveCredentials[connName];
        if (cached) {
            store.activeSshConnection = {name: connName, host: conn.host || '', user: conn.user || '', port: conn.port || 22};
            store.loading = true;
            try {
                store.entries = await fetchSshEntries(cached, path);
                store.filtered = store.entries;
                F.applyFilters();
            } catch (e) {
                delete store.sshActiveCredentials[connName];
                F.setSshStatus('error', 'Błąd odczytu pliku SSH: ' + e.message);
            } finally {
                store.loading = false;
            }
            return;
        }

        if (!conn) {
            F.setSshStatus('error', 'Nieznane połączenie SSH: ' + connName);
            return;
        }

        if (conn.authMethod === 'key') {
            const creds = credsFromConn(conn, '');
            store.loading = true;
            try {
                store.entries = await fetchSshEntries(creds, path);
                store.sshActiveCredentials[connName] = creds;
                store.filtered = store.entries;
                F.applyFilters();
            } catch (e) {
                F.setSshStatus('error', 'Błąd odczytu pliku SSH: ' + e.message);
            } finally {
                store.loading = false;
            }
            return;
        }

        store.showSSHModal = true;
        store.pendingSshRead = {connName, path};
        store.passwordModalPurpose = 'read';
        store.passwordForConnection = '';
        store.showPasswordModal = true;
    }

    async function performPendingSshRead() {
        const pending = store.pendingSshRead;
        const password = store.passwordForConnection;
        store.showPasswordModal = false;
        store.pendingSshRead = null;
        if (!pending) return;

        const conn = store.sshConnections.find(c => c.name === pending.connName);
        if (!conn) return;
        if (!password) {
            F.setSshStatus('error', 'SSH password is required for this connection');
            return;
        }

        const creds = credsFromConn(conn, password);
        store.loading = true;
        try {
            store.entries = await fetchSshEntries(creds, pending.path);
            store.sshActiveCredentials[pending.connName] = creds;
            store.filtered = store.entries;
            F.applyFilters();
        } catch (e) {
            F.setSshStatus('error', 'Błąd odczytu pliku SSH: ' + e.message);
        } finally {
            store.loading = false;
            store.passwordForConnection = '';
        }
    }

    async function submitPasswordModal() {
        if (store.passwordModalPurpose === 'read') {
            await performPendingSshRead();
        } else if (store.passwordModalPurpose === 'test') {
            await doTestSSHConnection();
        } else {
            await executeSSHConnection();
        }
    }

    function cancelPasswordModal() {
        store.showPasswordModal = false;
        store.pendingSshRead = null;
        store.passwordModalPurpose = 'connect';
        store.passwordForConnection = '';
        F.resetConnectionState();
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
            F.setSshStatus('error', 'Please enter a file path');
            return;
        }
        if (conn.authMethod === 'password' && !password) {
            F.setSshStatus('error', 'SSH password is required for this connection');
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
                F.setSshStatus('error', 'Failed to download file: ' + (downloadData.error || 'Unknown error'));
                return;
            }
            if (!store.sshFiles[conn.name]) store.sshFiles[conn.name] = [];
            if (!store.sshFiles[conn.name].some(f => f.file === downloadData.localPath)) {
                store.sshFiles[conn.name].push({
                    file: downloadData.localPath,
                    date: new Date().toISOString().split('T')[0],
                    size: downloadData.size,
                    sshLocal: true,
                });
            }
            const sshKey = 'ssh:' + conn.name;
            if (!store.directories.some(d => d.key === sshKey)) {
                store.directories.push({key: sshKey, path: sshKey, name: 'ssh-' + conn.name});
            }
            store.selectedDir = sshKey;
            store.files = store.sshFiles[conn.name];
            store.selectedFile = downloadData.localPath;
            await F.loadEntries();
            F.setSshStatus('success', `Pobrano ${filePath} (${downloadData.size} B), ${store.filtered.length} wpisów`);
        } catch (e) {
            F.setSshStatus('error', 'SSH operation failed: ' + e.message);
        } finally {
            F.resetConnectionState();
        }
    }

    function cancelManualFileModal() {
        store.showManualFileModal = false;
        F.resetConnectionState();
    }

    async function refreshSSHDir(dirKey) {
        if (!dirKey || !dirKey.startsWith('ssh:')) return;
        const connName = dirKey.replace('ssh:', '');
        const idx = store.sshConnections.findIndex(c => c.name === connName);
        if (idx < 0) return;
        const creds = store.sshActiveCredentials[connName];
        if (!creds) {
            connectSSH(idx);
            return;
        }
        await connectAndList(idx, creds.password || '');
    }

    function syncSSHDirs() {
        const conns = JSON.parse(localStorage.getItem('fplv_ssh_connections') || '[]');
        for (const conn of conns) {
            const key = 'ssh:' + conn.name;
            if (!store.directories.some(d => d.key === key)) {
                store.directories.push({key, path: key, name: 'ssh-' + conn.name});
            }
        }
    }

    Object.assign(F, {
        testSSHConnection, addSSHConnection, deleteSSHConnection,
        editSSHConnection, cancelEdit, openSSHModal, connectSSH,
        executeSSHConnection, submitPasswordModal, cancelPasswordModal,
        addManualSSHFile, executeManualFileAdd, cancelManualFileModal,
        refreshSSHDir, syncSSHDirs, loadSshFileEntries,
        setSshStatus, resetConnectionState,
    });
})();
