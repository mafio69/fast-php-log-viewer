/**
 * DirManagerModal - manage "Zapisane" (active) / "Odłożone" (deferred) directory
 * shortcuts and the "Config" allowed-containers list. Deleting/deferring/restoring
 * only - entries are added by actually browsing (KATALOG select, direct-file load,
 * or the container_not_allowed confirm), never typed into this panel.
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'DirManagerModal',
        props: ['store'],
        emits: ['close'],
        template: `
        <div v-if="store.showDirManager" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:480px;max-height:80vh;overflow-y:auto;">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold crt-glow">Zarządzanie katalogami</h3>
                    <button @click="$emit('close')" class="text-xs crt-button">✕</button>
                </div>

                <div class="flex gap-1 mb-4">
                    <button @click="store.dirManagerTab = 'saved'"
                        :style="store.dirManagerTab === 'saved' ? 'background:#00aa00;color:#000;' : 'background:#002200;color:#00aa00;'"
                        class="flex-1 rounded px-2 py-1 text-xs font-bold">Zapisane</button>
                    <button @click="store.dirManagerTab = 'deferred'"
                        :style="store.dirManagerTab === 'deferred' ? 'background:#00aa00;color:#000;' : 'background:#002200;color:#00aa00;'"
                        class="flex-1 rounded px-2 py-1 text-xs font-bold">Odłożone</button>
                    <button @click="store.dirManagerTab = 'config'"
                        :style="store.dirManagerTab === 'config' ? 'background:#00aa00;color:#000;' : 'background:#002200;color:#00aa00;'"
                        class="flex-1 rounded px-2 py-1 text-xs font-bold">Config</button>
                </div>

                <div v-if="store.dirManagerTab === 'saved'">
                    <div v-if="store.directories.length === 0" class="text-xs crt-dim">Brak zapisanych katalogów</div>
                    <div v-for="d in store.directories" :key="d.id" class="mb-2 p-2 flex justify-between items-center" style="background:#001100;border:1px solid #002200;">
                        <div class="text-xs crt-text truncate" :title="d.key" style="max-width:280px;">{{ formatDirLabel(d.key) }}</div>
                        <div class="flex gap-1 flex-shrink-0">
                            <button @click="deferDirectoryEntry(d.id)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ffff00;color:#ffff00;" title="Odłóż (nie w szybkim dostępie)">📥 Odłóż</button>
                            <button @click="deleteDirectoryEntry(d.id)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">🗑 Usuń</button>
                        </div>
                    </div>
                </div>

                <div v-if="store.dirManagerTab === 'deferred'">
                    <div v-if="store.deferredDirectories.length === 0" class="text-xs crt-dim">Brak odłożonych katalogów</div>
                    <div v-for="d in store.deferredDirectories" :key="d.id" class="mb-2 p-2 flex justify-between items-center" style="background:#001100;border:1px solid #002200;">
                        <div class="text-xs crt-dim truncate" :title="d.key" style="max-width:280px;">{{ formatDirLabel(d.key) }}</div>
                        <div class="flex gap-1 flex-shrink-0">
                            <button @click="restoreDirectoryEntry(d.id)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#00ff00;color:#00ff00;" title="Przywróć do szybkiego dostępu">↩ Przywróć</button>
                            <button @click="deleteDirectoryEntry(d.id)" class="crt-button px-2 py-1 text-xs rounded" style="border-color:#ff0000;color:#ff0000;">🗑 Usuń</button>
                        </div>
                    </div>
                </div>

                <div v-if="store.dirManagerTab === 'config'">
                    <div class="text-xs crt-dim mb-3">
                        Dozwolone kontenery Docker. Dodawanie tylko przez próbę przeglądania katalogu
                        (aplikacja zapyta o potwierdzenie) - tutaj można wyłącznie usuwać.
                    </div>
                    <div v-if="store.allowedContainersList.length === 0" class="text-xs crt-dim">Brak dozwolonych kontenerów</div>
                    <div v-for="c in store.allowedContainersList" :key="c.id" class="mb-2 p-2 flex justify-between items-center" style="background:#001100;border:1px solid #002200;">
                        <div class="text-xs crt-text truncate" :title="c.container_id" style="max-width:280px;">🐳 {{ c.container_id }}</div>
                        <button @click="deleteAllowedContainerEntry(c.id)" class="crt-button px-2 py-1 text-xs rounded flex-shrink-0" style="border-color:#ff0000;color:#ff0000;">🗑 Usuń</button>
                    </div>

                    <div class="text-xs font-semibold mt-4 mb-2 crt-dim">Dozwolone ścieżki (w obrębie kontenera)</div>
                    <div v-if="store.allowedPathsList.length === 0" class="text-xs crt-dim">Brak dozwolonych ścieżek</div>
                    <div v-for="p in store.allowedPathsList" :key="p.id" class="mb-2 p-2 flex justify-between items-center" style="background:#001100;border:1px solid #002200;">
                        <div class="text-xs crt-text truncate" :title="p.path_prefix" style="max-width:280px;">📁 {{ p.path_prefix }}</div>
                        <button @click="deleteAllowedPathEntry(p.id)" class="crt-button px-2 py-1 text-xs rounded flex-shrink-0" style="border-color:#ff0000;color:#ff0000;">🗑 Usuń</button>
                    </div>
                </div>
            </div>
        </div>
        `,
        setup() {
            return {
                formatDirLabel: F.formatDirLabel,
                deferDirectoryEntry: F.deferDirectoryEntry,
                restoreDirectoryEntry: F.restoreDirectoryEntry,
                deleteDirectoryEntry: F.deleteDirectoryEntry,
                deleteAllowedContainerEntry: F.deleteAllowedContainerEntry,
                deleteAllowedPathEntry: F.deleteAllowedPathEntry,
            };
        }
    });
})();
