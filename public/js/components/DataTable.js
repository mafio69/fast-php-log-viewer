/**
 * DataTable component - log entries table with pagination, sorting, expand
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'DataTable',
        props: ['store'],
        emits: ['toggle-table-sort', 'set-table-page', 'set-table-page-size', 'table-prev-page', 'table-next-page', 'toggle-expand', 'toggle-bookmark'],
        template: `
        <div v-if="store.loading" class="flex-1 flex items-center justify-center crt-dim">Loading…</div>
        <div v-else-if="!store.selectedFile" class="flex-1 flex items-center justify-center crt-dim">Select a log file.</div>
        <div v-else-if="!store.filtered.length" class="flex-1 flex items-center justify-center crt-dim">No entries match filters.</div>
        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2" style="background:#001100;border-bottom:1px solid #002200;">
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">Pokaż:</span>
                    <select v-model="store.tablePageSize" @change="$emit('set-table-page-size', parseInt($event.target.value))"
                            class="crt-input text-xs px-2 py-1 rounded">
                        <option v-for="size in store.TABLE_PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
                    </select>
                    <span class="text-xs crt-dim">na stronę</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">{{ tableStartRow }}-{{ tableEndRow }} z {{ store.filtered.length }}</span>
                    <button @click="$emit('table-prev-page')" :disabled="store.tablePage === 1" class="px-2 py-1 text-xs crt-button"
                            :style="store.tablePage === 1 ? 'opacity:0.3;cursor:not-allowed;' : ''">←</button>
                    <span class="text-xs crt-text">Strona {{ store.tablePage }} / {{ tableTotalPages }}</span>
                    <button @click="$emit('table-next-page')" :disabled="store.tablePage === tableTotalPages"
                            class="px-2 py-1 text-xs crt-button"
                            :style="store.tablePage === tableTotalPages ? 'opacity:0.3;cursor:not-allowed;' : ''">→</button>
                </div>
            </div>
            <div class="flex-1 overflow-auto">
                <table class="w-full text-sm border-collapse">
                    <thead style="background:#001100;border-bottom:1px solid #00ff00;" class="sticky top-0 z-10">
                    <tr>
                        <th @click="$emit('toggle-table-sort', 'datetime')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow" style="width:155px;">
                            Datetime {{ store.tableSortColumn === 'datetime' ? (store.tableSortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th @click="$emit('toggle-table-sort', 'level')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow" style="width:90px;">
                            Level {{ store.tableSortColumn === 'level' ? (store.tableSortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th @click="$emit('toggle-table-sort', 'location')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow" style="width:200px;">
                            Location {{ store.tableSortColumn === 'location' ? (store.tableSortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th @click="$emit('toggle-table-sort', 'message')"
                            class="text-left px-3 py-2 font-medium text-xs crt-dim cursor-pointer hover:crt-glow">
                            Message {{ store.tableSortColumn === 'message' ? (store.tableSortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="(entry, i) in tablePaginatedData" :key="tableStartRow + i - 1">
                        <tr @click="$emit('toggle-expand', tableStartRow + i - 1)" class="cursor-pointer"
                            :style="'border-bottom:1px solid #002200;' + rowBg(entry.level)">
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">{{ formatDate(entry.datetime) }}</td>
                            <td class="px-3 py-1.5 text-xs font-bold whitespace-nowrap" :style="'color:' + levelColor(entry.level)">{{ entry.level }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap crt-dim">
                                <a v-if="store.editorUrl && entry.location"
                                   :href="openInEditor(entry.location)" @click.stop
                                   class="hover:underline" :style="'color:' + levelColor(entry.level)">{{ entry.location }}</a>
                                <span v-else>{{ entry.location }}</span>
                            </td>
                            <td class="px-3 py-1.5 truncate max-w-0 w-full crt-text">
                                <span class="block truncate">
                                    <span v-if="isBookmarked(entry)" style="color:#ffff00;" title="Zakładka">★ </span>{{ entry.message }}
                                </span>
                                <span class="text-xs crt-dim">{{ store.expanded[tableStartRow + i - 1] ? '▲' : '▼' }}</span>
                            </td>
                        </tr>
                        <tr v-if="store.expanded[tableStartRow + i - 1]"
                            style="background:#001100;border-bottom:1px solid #002200;">
                            <td colspan="4" class="px-3 py-2">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <div class="text-sm mb-1 crt-text" style="white-space:pre-wrap;word-break:break-word;">{{ entry.message }}</div>
                                        <div v-if="entry.location" class="text-xs mb-1 crt-dim">📍 {{ entry.location }}</div>
                                        <pre v-if="hasContext(entry)" class="text-xs font-mono whitespace-pre-wrap crt-dim">{{ JSON.stringify(entry.context, null, 2) }}</pre>
                                    </div>
                                    <button @click.stop="$emit('toggle-bookmark', entry)"
                                            class="text-lg flex-shrink-0 crt-button"
                                            :title="isBookmarked(entry) ? 'Usuń zakładkę' : 'Dodaj zakładkę'"
                                            :style="isBookmarked(entry) ? 'border-color:#ffff00;color:#ffff00;' : 'border-color:#006600;color:#006600;'">★</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-4 py-2" style="background:#001100;border-top:1px solid #002200;">
                <div class="flex items-center gap-2">
                    <span class="text-xs crt-dim">Idź do strony:</span>
                    <input type="number" v-model.number="store.tablePage"
                           @change="$emit('set-table-page', Math.max(1, Math.min(tableTotalPages, $event.target.value)))" min="1"
                           :max="tableTotalPages" class="crt-input text-xs px-2 py-1 rounded" style="width:60px;">
                </div>
                <div class="flex items-center gap-1">
                    <button v-for="p in Math.min(10, tableTotalPages)" :key="p" @click="$emit('set-table-page', p)"
                            class="px-2 py-1 text-xs crt-button"
                            :style="p === store.tablePage ? 'background:#00ff00;color:#000;' : ''">{{ p }}</button>
                    <span v-if="tableTotalPages > 10" class="text-xs crt-dim">... ({{ tableTotalPages }})</span>
                </div>
            </div>
        </div>
        `,
        setup() {
            return {
                tablePaginatedData: F.tablePaginatedData,
                tableTotalPages: F.tableTotalPages,
                tableStartRow: F.tableStartRow,
                tableEndRow: F.tableEndRow,
                levelColor: F.levelColor,
                levelDot: F.levelDot,
                rowBg: F.rowBg,
                hasContext: F.hasContext,
                openInEditor: F.openInEditor,
                formatDate: F.formatDate,
                isBookmarked: F.isBookmarked,
            };
        }
    });
})();
