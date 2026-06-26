/**
 * Toolbar component - search, level filters, sort, date range, bookmarks
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'Toolbar',
        props: ['store'],
        emits: ['toggle-level', 'toggle-sort', 'apply-filters', 'load-entries', 'update-font-size', 'go-to-bookmark', 'remove-bookmark', 'toggle-bookmarks', 'toggle-level-filters'],
        template: `
        <div class="flex items-center gap-2 px-4 py-2" style="background:#000;border-bottom:1px solid #00ff00;">
            <input v-model="store.filterText" @input="$emit('apply-filters')" placeholder="Search…"
                class="rounded px-3 py-1 text-sm flex-1 max-w-xs crt-input">
            <div class="flex items-center gap-1">
                <button v-for="level in ['DEBUG','INFO','NOTICE']" :key="level"
                        @click="$emit('toggle-level', level)"
                        class="px-2 py-1 text-xs rounded crt-button"
                        :style="store.excludedLevels.includes(level) ? 'border-color:#003300;color:#003300;' : 'border-color:' + levelDot(level) + ';color:' + levelColor(level)">
                    {{ level }}
                </button>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <button @click="$emit('toggle-level-filters')" class="px-2 py-1 text-xs crt-button">FILTRY ▼</button>
                    <div v-if="store.showLevelFilters" class="absolute left-0 top-full mt-1 rounded shadow-lg z-20 p-3"
                         style="background:#000;border:1px solid #00ff00;min-width:250px;">
                        <div class="mb-3 pb-2" style="border-bottom:1px solid #002200;">
                            <div class="text-xs crt-dim mb-1">Sortowanie</div>
                            <button @click="$emit('toggle-sort')" class="w-full px-2 py-1 text-xs crt-button">
                                {{ store.sortOrder === 'desc' ? '↓ Najnowsze na górze' : '↑ Najstarsze na górze' }}
                            </button>
                        </div>
                        <div class="mb-3 pb-2" style="border-bottom:1px solid #002200;">
                            <div class="text-xs crt-dim mb-1">Zakres daty</div>
                            <div class="flex items-center gap-1 mb-1">
                                <input type="date" v-model="store.dateFrom" @change="$emit('apply-filters')" class="px-1 py-0.5 text-xs crt-input flex-1">
                            </div>
                            <div class="flex items-center gap-1">
                                <input type="date" v-model="store.dateTo" @change="$emit('apply-filters')" class="px-1 py-0.5 text-xs crt-input flex-1">
                            </div>
                        </div>
                        <div class="text-xs crt-dim mb-1">Poziomy logów</div>
                        <label v-for="level in LEVELS" :key="level"
                               class="flex items-center gap-2 text-xs cursor-pointer crt-dim mb-1">
                            <span class="w-2 h-2 rounded-full inline-block" :style="'background:' + levelDot(level)"></span>
                            <input type="checkbox" :checked="!store.excludedLevels.includes(level)" @change="$emit('toggle-level', level)" class="hidden">
                            <span @click="$emit('toggle-level', level)" :style="store.excludedLevels.includes(level) ? 'color:#003300;' : 'color:#00ff00;'">{{ level }}</span>
                            <span class="ml-auto crt-dim">{{ levelCounts[level] || '' }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <button @click="$emit('load-entries')" title="Refresh" class="px-3 py-1 rounded text-sm crt-button">↺</button>
            <div class="flex items-center gap-1 rounded overflow-hidden crt-border">
                <button @click="$emit('update-font-size', Math.max(10, store.fontSize - 1))" class="px-2 py-1 text-xs crt-button">A−</button>
                <span class="px-2 text-xs crt-dim">{{ store.fontSize }}px</span>
                <button @click="$emit('update-font-size', Math.min(24, store.fontSize + 1))" class="px-2 py-1 text-xs crt-button">A+</button>
            </div>
            <div class="relative" style="margin-left:auto;">
                <button @click="$emit('toggle-bookmarks')" class="px-3 py-1 rounded text-sm flex items-center gap-1 crt-button" style="border-color:#ffff00;color:#ffff00;">
                    ★ <span class="crt-dim">{{ store.bookmarks.length }}</span>
                </button>
                <div v-if="store.showBookmarks" class="absolute right-0 top-full mt-1 rounded shadow-lg z-20 overflow-hidden"
                    style="background:#000;border:1px solid #00ff00;width:380px;max-height:320px;overflow-y:auto;">
                    <div v-if="!store.bookmarks.length" class="px-3 py-2 text-xs crt-dim">Brak zakładek</div>
                    <div v-for="(bm, bi) in store.bookmarks" :key="bi"
                        class="px-3 py-2 cursor-pointer flex items-center gap-2"
                        style="border-bottom:1px solid #002200;"
                        @click="$emit('go-to-bookmark', bm)">
                        <span class="text-xs font-bold flex-shrink-0" :style="'color:' + levelColor(bm.level)">{{ bm.level }}</span>
                        <span class="text-xs truncate flex-1 crt-text">{{ bm.message }}</span>
                        <span class="text-xs flex-shrink-0 crt-dim">{{ bm.file.split('/').pop() }}</span>
                        <button @click.stop="$emit('remove-bookmark', bi)" class="text-xs crt-button" style="border-color:#ff0000;color:#ff0000;" title="Usuń">✕</button>
                    </div>
                </div>
            </div>
        </div>
        `,
        setup() {
            return {
                LEVELS: F.LEVELS,
                levelColor: F.levelColor,
                levelDot: F.levelDot,
                levelCounts: F.levelCounts,
            };
        }
    });
})();
