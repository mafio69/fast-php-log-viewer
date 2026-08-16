/**
 * AuthModal - login/register dialog
 *
 * Login is ONLY for private SSH connection storage (business-rules.md,
 * Wymaganie 3, kryterium 4). The notice below is mandatory - it tells the
 * user this is not a general-purpose account system.
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;

    F.components.push({
        name: 'AuthModal',
        props: ['store'],
        emits: ['close', 'submit', 'switch-mode'],
        template: `
        <div v-if="store.showAuthModal" class="fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.8);">
            <div class="rounded shadow-lg p-4" style="background:#000;border:1px solid #00ff00;width:380px;">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-sm font-bold crt-glow">{{ store.authMode === 'register' ? 'REJESTRACJA' : 'LOGOWANIE' }}</h3>
                    <button @click="$emit('close')" class="text-xs crt-button">✕</button>
                </div>

                <div class="mb-3 p-2 text-xs" style="border:1px solid #0066cc;background:#001133;color:#6699cc;">
                    ℹ Logowanie służy <b>wyłącznie</b> do prywatnego przechowywania Twoich połączeń SSH.
                    Nie jest ogólnym systemem kont i nie ogranicza dostępu do innych funkcji aplikacji.
                </div>

                <div v-if="store.authError" class="mb-3 p-2 text-xs" style="border:1px solid #ff0000;color:#ff6666;">
                    ⚠ {{ store.authError }}
                </div>

                <div class="flex flex-col gap-2 mb-3">
                    <input v-model="store.authForm.username" placeholder="Nazwa użytkownika"
                        autocomplete="username" class="crt-input px-2 py-1 text-xs rounded">
                    <input v-model="store.authForm.password" type="password" placeholder="Hasło"
                        :autocomplete="store.authMode === 'register' ? 'new-password' : 'current-password'"
                        class="crt-input px-2 py-1 text-xs rounded">
                </div>

                <button @click="$emit('submit')" :disabled="store.authLoading"
                    class="w-full rounded px-2 py-1 text-xs crt-button font-bold mb-2"
                    :style="store.authLoading ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    {{ store.authLoading ? '...' : (store.authMode === 'register' ? 'Zarejestruj' : 'Zaloguj') }}
                </button>

                <div class="text-center">
                    <button @click="$emit('switch-mode')" class="text-xs crt-button" style="border:none;background:none;">
                        {{ store.authMode === 'register' ? 'Masz konto? Zaloguj się' : 'Nie masz konta? Zarejestruj się' }}
                    </button>
                </div>
            </div>
        </div>
        `,
    });
})();
