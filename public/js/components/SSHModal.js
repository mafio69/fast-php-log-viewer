/**
 * SSHModal component - SSH connections management with sub-modals
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'SshModal',
        props: ['store'],
        emits: ['close', 'test-connection', 'add-connection', 'delete-connection', 'edit-connection', 'cancel-edit', 'connect', 'execute-connection', 'cancel-password', 'add-manual-file', 'execute-manual-file-add', 'cancel-manual-file'],
        template: `
        <div v-if="store.showSSHModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:500px;max-height:80vh;overflow-y:auto;">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold crt-glow">SSH Connections</h3>
                    <button @click="$emit('close')" class="text-xs crt-button">✕</button>
                </div>
                <div class="mb-4 p-3" style="background:#001100;border:1px solid #00ff00;">
                    <h4 class="text-xs font-bold mb-2 crt-text">{{ store.editingIndex >= 0 ? 'Edit SSH Connection' : 'Add New SSH Connection' }}</h4>
                    <div class="flex flex-col gap-2">
                        <input v-model="store.sshForm.name" placeholder="Connection Name" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.sshForm.host" placeholder="SSH Host" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.sshForm.user" placeholder="SSH User" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.sshForm.port" placeholder="SSH Port (default: 22)" class="crt-input px-2 py-1 text-xs rounded">
                        <select v-model="store.sshForm.authMethod" class="crt-input px-2 py-1 text-xs rounded">
                            <option value="password">Password Authentication</option>
                            <option value="key">SSH Key Authentication</option>
                        </select>
                        <input v-if="store.sshForm.authMethod === 'password'" v-model="store.sshForm.password" type="password" placeholder="SSH Password" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-if="store.sshForm.authMethod === 'key'" v-model="store.sshForm.keyPath" placeholder="SSH Key Path (default: ~/.ssh/id_rsa)" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-if="store.sshForm.authMethod === 'key'" v-model="store.sshForm.keyPassphrase" type="password" placeholder="Key Passphrase (optional)" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.sshForm.remotePath" placeholder="Remote Log Path (e.g., /var/log)" class="crt-input px-2 py-1 text-xs rounded">
                        <label class="flex items-center gap-2 text-xs crt-text">
                            <input type="checkbox" v-model="store.sshForm.allFiles" class="crt-input"> Show all files (no pattern filtering)
                        </label>
                        <div class="flex gap-2">
                            <button @click="$emit('test-connection')" class="flex-1 crt-button py-1 text-xs rounded">Test Connection</button>
                            <button v-if="store.editingIndex >= 0" @click="$emit('cancel-edit')" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                            <button @click="$emit('add-connection')" class="flex-1 crt-button py-1 text-xs rounded">{{ store.editingIndex >= 0 ? 'Update Connection' : 'Save Connection' }}</button>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-xs font-bold mb-2 crt-text">Saved Connections</h4>
                    <div v-if="store.sshConnections.length === 0" class="text-xs crt-dim">No SSH connections saved</div>
                    <div v-for="(conn, idx) in store.sshConnections" :key="idx" class="mb-2 p-2" style="background:#001100;border:1px solid #002200;">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-xs font-bold crt-text">{{ conn.name }}</div>
                                <div class="text-xs crt-dim">{{ conn.user }}@{{ conn.host }}:{{ conn.port || 22 }}</div>
                                <div class="text-xs crt-dim">Path: {{ conn.remotePath }}</div>
                            </div>
                            <div class="flex gap-1">
                                <button @click="$emit('connect', idx)" class="crt-button px-2 py-1 text-xs rounded">Connect</button>
                                <button @click="$emit('add-manual-file', idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#00ffff;color:#00ffff;">Download File</button>
                                <button @click="$emit('edit-connection', idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ffff00;color:#ffff00;">Edit</button>
                                <button @click="$emit('delete-connection', idx)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="store.showPasswordModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:400px;">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold crt-glow">SSH Password</h3>
                    <button @click="$emit('cancel-password')" class="text-xs crt-button">✕</button>
                </div>
                <div class="mb-4">
                    <label class="block text-xs crt-text mb-2">Enter SSH password (or leave empty for key auth):</label>
                    <input v-model="store.passwordForConnection" type="password" placeholder="Password" class="crt-input px-2 py-1 text-xs rounded w-full" @keyup.enter="$emit('execute-connection')">
                </div>
                <div class="flex gap-2">
                    <button @click="$emit('cancel-password')" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                    <button @click="$emit('execute-connection')" class="flex-1 crt-button py-1 text-xs rounded">Connect</button>
                </div>
            </div>
        </div>
        <div v-if="store.showManualFileModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ffff;width:400px;">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold crt-glow" style="color:#00ffff;">Download SSH File</h3>
                    <button @click="$emit('cancel-manual-file')" class="text-xs crt-button">✕</button>
                </div>
                <div class="mb-4">
                    <label class="block text-xs crt-text mb-2">SSH Password:</label>
                    <input v-model="store.passwordForConnection" type="password" placeholder="Enter password" class="crt-input px-2 py-1 text-xs rounded w-full mb-3">
                    <label class="block text-xs crt-text mb-2">Remote file path:</label>
                    <input v-model="store.manualFilePath" placeholder="/var/log/demo.log" class="crt-input px-2 py-1 text-xs rounded w-full" @keyup.enter="$emit('execute-manual-file-add')">
                    <p class="text-xs crt-dim mt-2">File will be downloaded to temp/ and added to file list</p>
                </div>
                <div class="flex gap-2">
                    <button @click="$emit('cancel-manual-file')" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">Cancel</button>
                    <button @click="$emit('execute-manual-file-add')" class="flex-1 crt-button py-1 text-xs rounded" style="border-color:#00ffff;color:#00ffff;">Download</button>
                </div>
            </div>
        </div>
        `,
    });
})();
