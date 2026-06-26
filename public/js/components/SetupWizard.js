/**
 * SetupWizard component - first-run configuration wizard
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'SetupWizard',
        props: ['store'],
        emits: ['proceed-step', 'toggle-skip-confirm', 'clear-step-data'],
        template: `
        <div class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.95);">
            <div class="rounded shadow-lg p-6" style="background:#000;border:1px solid #00ff00;width:600px;">
                <h2 class="text-sm font-bold crt-glow mb-4">⚡ KONFIGURACJA APLIKACJI</h2>
                <div v-if="store.setupWarning" class="mb-3 p-2 text-xs" style="border:1px solid #ffff00;color:#ffff00;">
                    ⚠ {{ store.setupWarning }}
                </div>
                <div v-if="store.currentSetupStep === 'generate_keys'">
                    <p class="text-xs crt-text mb-4">Aplikacja wygeneruje klucz szyfrowania i unikalny ID instalacji.</p>
                    <div v-if="!store.setupSkipConfirm" class="flex gap-2">
                        <button @click="$emit('proceed-step', false)" class="flex-1 crt-button py-1 text-xs">Generuj klucze</button>
                        <button @click="$emit('toggle-skip-confirm', true)" class="flex-1 crt-button py-1 text-xs" style="border-color:#ff6600;color:#ff6600;">Pomiń</button>
                    </div>
                    <div v-else class="flex gap-2">
                        <p class="text-xs" style="color:#ff6600;">Backup nie będzie szyfrowany. Potwierdź pominięcie:</p>
                        <button @click="$emit('proceed-step', true)" class="crt-button py-1 text-xs px-3" style="border-color:#ff0000;color:#ff0000;">Rozumiem, pomiń</button>
                        <button @click="$emit('toggle-skip-confirm', false)" class="crt-button py-1 text-xs px-3">Wróć</button>
                    </div>
                </div>
                <div v-if="store.currentSetupStep === 'ssh_config'">
                    <p class="text-xs crt-text mb-4">Skonfiguruj połączenie SSH (opcjonalne).</p>
                    <div v-if="!store.setupSkipConfirm" class="flex flex-col gap-2 mb-4">
                        <input v-model="store.setupStepData.ssh_host" placeholder="Host (np. 192.168.1.100)" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.setupStepData.ssh_user" placeholder="Użytkownik SSH" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-model="store.setupStepData.ssh_port" placeholder="Port (domyślnie 22)" class="crt-input px-2 py-1 text-xs rounded">
                        <select v-model="store.setupStepData.ssh_auth_method" class="crt-input px-2 py-1 text-xs rounded">
                            <option value="password">Uwierzytelnianie hasłem</option>
                            <option value="key">Klucz SSH</option>
                        </select>
                        <input v-if="store.setupStepData.ssh_auth_method === 'password'" v-model="store.setupStepData.ssh_password" type="password" placeholder="Hasło SSH" class="crt-input px-2 py-1 text-xs rounded">
                        <input v-if="store.setupStepData.ssh_auth_method === 'key'" v-model="store.setupStepData.ssh_key_path" placeholder="Ścieżka do klucza (np. /home/user/.ssh/id_rsa)" class="crt-input px-2 py-1 text-xs rounded">
                        <div class="flex gap-2 mt-2">
                            <button @click="$emit('proceed-step', false)" class="flex-1 crt-button py-1 text-xs">Dalej</button>
                            <button @click="$emit('toggle-skip-confirm', true)" class="flex-1 crt-button py-1 text-xs" style="border-color:#ff6600;color:#ff6600;">Pomiń</button>
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2 mb-4">
                        <p class="text-xs" style="color:#ff6600;">Funkcja SSH zostanie wyłączona. Będziesz mógł skonfigurować SSH później w ustawieniach.</p>
                        <div class="flex gap-2">
                            <button @click="$emit('proceed-step', true)" class="flex-1 crt-button py-1 text-xs" style="border-color:#ff0000;color:#ff0000;">Rozumiem, pomiń</button>
                            <button @click="$emit('toggle-skip-confirm', false)" class="flex-1 crt-button py-1 text-xs">Wróć</button>
                        </div>
                    </div>
                </div>
                <div v-if="store.currentSetupStep === 'local_directories'">
                    <p class="text-xs crt-text mb-4">Dodaj katalog z logami (opcjonalne).</p>
                    <div v-if="!store.setupSkipConfirm">
                        <input v-model="store.setupStepData.path" placeholder="Ścieżka katalogu (np. /var/log)" class="crt-input px-2 py-1 text-xs rounded w-full mb-4">
                        <div class="flex gap-2">
                            <button @click="$emit('proceed-step', false)" class="flex-1 crt-button py-1 text-xs">Dalej</button>
                            <button @click="$emit('toggle-skip-confirm', true)" class="flex-1 crt-button py-1 text-xs" style="border-color:#ff6600;color:#ff6600;">Pomiń</button>
                        </div>
                    </div>
                    <div v-else>
                        <p class="text-xs" style="color:#ff6600;">Brak skonfigurowanych katalogów lokalnych. Możesz dodać katalogi później w ustawieniach.</p>
                        <div class="flex gap-2 mt-4">
                            <button @click="$emit('proceed-step', true)" class="flex-1 crt-button py-1 text-xs" style="border-color:#ff0000;color:#ff0000;">Rozumiem, pomiń</button>
                            <button @click="$emit('toggle-skip-confirm', false)" class="flex-1 crt-button py-1 text-xs">Wróć</button>
                        </div>
                    </div>
                </div>
                <div v-if="store.currentSetupStep === 'finalize'">
                    <p class="text-xs crt-text mb-4">Konfiguracja jest gotowa do zakończenia.</p>
                    <button @click="$emit('proceed-step', false)" class="w-full crt-button py-1 text-xs">Zakończ konfigurację</button>
                </div>
            </div>
        </div>
        `,
    });
})();
