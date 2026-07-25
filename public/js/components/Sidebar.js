/**
 * Sidebar component - directory selector, file list, direct file path, add dir, SSH button
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'Sidebar',
        props: ['store'],
        emits: ['select-file', 'change-dir', 'load-direct-file', 'refresh-ssh-dir', 'open-ssh-modal', 'cancel-edit'],
        template: `
        <aside style="width:350px;min-width:350px;background:#000;border-right:1px solid #00ff00;" class="flex flex-col">
            <div class="px-3 py-3 crt-border" style="border-bottom:1px solid #00ff00;">
                <div class="font-bold text-sm crt-glow">⚡ LOG-VIEWER</div>
            </div>
            <div style="border-top:1px solid #00ff00;" class="px-3 py-2">
                <div class="text-xs crt-dim">
                    {{ store.filtered.length }} entries<br>
                    <span v-if="store.selectedFile">{{ store.selectedFile.split('/').pop() }}</span>
                </div>
            </div>
            <div v-if="store.defaultDirectories.length" class="px-3 py-2" style="border-bottom:1px solid #00ff00;">
                <div class="text-xs font-semibold mb-1 crt-dim">KATALOG</div>
                <div class="flex gap-2">
                    <select v-model="store.selectedDir" @change="$emit('change-dir')" class="flex-1 rounded px-2 py-1 text-xs crt-input" style="min-width:0;">
                        <optgroup v-for="(group, gkey) in mergedDirectories" :key="gkey" :label="group.label">
                            <option v-for="d in group.items" :key="d.key" :value="d.key" :title="d.key">{{ formatDirLabel(d.key) }}</option>
                        </optgroup>
                    </select>
                    <button v-if="store.selectedDir && store.selectedDir.startsWith('ssh:')" @click="$emit('refresh-ssh-dir', store.selectedDir)" class="px-2 py-1 text-xs crt-button" title="Odśwież">↻</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto" style="flex:6;">
                <div v-if="store.files.length === 0" class="px-3 py-8 text-center crt-dim">pusto</div>
                <div v-for="f in store.files" :key="f.file"
                    tabindex="0"
                    role="button"
                    @click="$emit('select-file', f.file)"
                    @keydown.enter.prevent="$emit('select-file', f.file)"
                    @keydown.space.prevent="$emit('select-file', f.file)"
                    class="px-3 py-2 cursor-pointer"
                    style="border-bottom:1px solid #002200;outline:none;"
                    :style="store.selectedFile === f.file ? 'background:#002200;border-left:3px solid #00ff00;color:#00ff00;' : 'color:#006600;border-left:3px solid transparent;'">
                    <div class="font-medium truncate text-xs">{{ f.file.split('/').pop() }}</div>
                    <div class="crt-dim text-xs">{{ formatDate(f.date) }} · {{ formatSize(f.size) }}</div>
                    <div v-if="f.allow" class="crt-dim text-xs">allow: {{ f.allow }}</div>
                </div>
            </div>
            <div class="px-3 py-3" style="border-bottom:1px solid #00ff00;background:#001100;">
                <div class="text-xs font-bold mb-2 crt-text">📂 ŚCIEŻKA DO {{ store.directFileMode === 'docker' ? 'KATALOGU' : 'PLIKU' }}</div>
                <input v-if="store.directFileMode !== 'host'" type="text" v-model="store.containerId" placeholder="container_name (opcjonalnie)"
                    name="fplv_container_id" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                    class="w-full rounded px-2 py-1 text-xs crt-input mb-2" style="color:#00aacc;">
                <div class="flex gap-1 mb-2">
                    <button @click="store.directFileMode = 'docker'"
                        :style="store.directFileMode === 'docker' ? 'background:#00aa00;color:#000;' : 'background:#002200;color:#00aa00;'"
                        class="flex-1 rounded px-2 py-1 text-xs font-bold">🐳 DOCKER</button>
                    <button @click="store.directFileMode = 'host'; store.containerId = ''"
                        :style="store.directFileMode === 'host' ? 'background:#0066cc;color:#fff;' : 'background:#001133;color:#0066cc;'"
                        class="flex-1 rounded px-2 py-1 text-xs font-bold">💻 HOST</button>
                </div>
                <div v-if="store.containerId && store.containerCheckStatus" class="text-xs mb-2" :style="containerCheckStyle">
                    {{ containerCheckLabel }}
                </div>
                <input type="text" v-model="store.directFilePath"
                    :placeholder="store.directFileMode === 'docker' ? '/var/log' : '/var/log/php/php_errors.log'"
                    name="fplv_direct_file_path" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                    class="w-full rounded px-2 py-1 text-xs crt-input mb-2">
                <button @click="$emit('load-direct-file')" class="w-full rounded px-2 py-1 text-xs crt-button font-bold">⚡ ZAŁADUJ</button>
            </div>
            <div class="px-3 py-2" style="border-top:1px solid #00ff00;">
                <button @click="$emit('open-ssh-modal'); $emit('cancel-edit')" class="w-full rounded py-1 text-xs crt-button">🔗 SSH Connections</button>
            </div>
        </aside>
        `,
        setup(props) {
            const CONTAINER_CHECK_LABELS = {
                checking: '⏳ sprawdzam...',
                ok: '✅ kontener OK',
                not_found: '❌ kontener nie znaleziony',
                not_allowed: '❌ kontener niedozwolony',
                path_not_allowed: '❌ ścieżka niedozwolona',
                error: '❌ błąd sprawdzania',
            };
            const CONTAINER_CHECK_COLORS = {
                checking: '#cccc00',
                ok: '#00ff00',
                not_found: '#ff6600',
                not_allowed: '#ff6600',
                path_not_allowed: '#ff6600',
                error: '#ff6600',
            };

            return {
                mergedDirectories: F.mergedDirectories,
                formatDate: F.formatDate,
                formatSize: F.formatSize,
                formatDirLabel: F.formatDirLabel,
                containerCheckLabel: Vue.computed(() => CONTAINER_CHECK_LABELS[props.store.containerCheckStatus] || ''),
                containerCheckStyle: Vue.computed(() => 'color:' + (CONTAINER_CHECK_COLORS[props.store.containerCheckStatus] || '#00aacc') + ';'),
            };
        }
    });
})();
