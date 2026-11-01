/**
 * PasteLogsModal — paste raw log text for parsing without a file on disk.
 *
 * Use case: the host's /var/log is not mounted into the container (default
 * setup), so the user cannot browse host log files directly. Instead of
 * mounting volumes, they open the log on the host, copy the contents, and
 * paste them here — the backend parses the text into the same structured
 * entries a file read would produce.
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'PasteLogsModal',
        props: ['store'],
        emits: ['close', 'parse'],
        template: `
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg flex flex-col" style="background:#000;border:1px solid #00ff00;width:720px;max-width:90vw;max-height:80vh;">
                <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid #00ff00;">
                    <div class="font-bold text-sm crt-glow">📋 Wklej logi</div>
                    <button @click="$emit('close')" class="text-lg crt-button" style="border-color:#ff6600;color:#ff6600;">✕</button>
                </div>
                <div class="flex-1 overflow-auto p-4">
                    <div class="text-xs crt-dim mb-2">
                        Wklej treść logu z hosta (lub z dowolnego źródła). Aplikacja sparsuje tekst tak samo jak plik na dysku — bez montowania wolumenu.
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs crt-dim">Poziom:</span>
                        <select v-model="store.pastedLogsLevel" class="crt-input text-xs px-2 py-1 rounded">
                            <option value="">wszystkie</option>
                            <option v-for="level in LEVELS" :key="level" :value="level">{{ level }}</option>
                        </select>
                    </div>
                    <textarea v-model="store.pastedLogsContent"
                        placeholder="[2026-08-16 14:25:00] [WARNING] [app/index.php:42] Message {&quot;k&quot;:&quot;v&quot;}&#10;..."
                        class="w-full crt-input rounded px-3 py-2 font-mono text-xs"
                        style="height:300px;resize:vertical;white-space:pre;overflow:auto;"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 px-4 py-3" style="border-top:1px solid #00ff00;">
                    <button @click="$emit('close')" class="px-3 py-1 text-xs crt-button">Anuluj</button>
                    <button @click="$emit('parse')" class="px-4 py-1 text-xs rounded font-bold" style="background:#00ff00;color:#000;">⚡ Parsuj</button>
                </div>
            </div>
        </div>
        `,
        setup() {
            return {
                LEVELS: F.LEVELS,
            };
        }
    });
})();
