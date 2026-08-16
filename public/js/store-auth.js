/**
 * store-auth.js — user authentication, session management
 *
 * Login exists ONLY to give a user a private scope for storing SSH
 * connections (docs/business-rules.md, Wymaganie 3, kryterium 4). It is not
 * a general-purpose account system and gates nothing else in the app.
 */
window.FPLV = window.FPLV || {};

(function () {
    const F = window.FPLV;
    const store = F.store;

    Object.assign(store, {
        currentUser: null,
        showAuthModal: false,
        authMode: 'login',
        authForm: {username: '', password: ''},
        authError: '',
        authLoading: false,
    });

    async function checkSession() {
        try {
            const r = await F.fetchJson('/api/auth/session');
            store.currentUser = r.authenticated ? r.user : null;
        } catch (e) {
            store.currentUser = null;
        }
    }

    function openAuthModal(mode) {
        store.authMode = mode || 'login';
        store.authForm = {username: '', password: ''};
        store.authError = '';
        store.showAuthModal = true;
    }

    async function submitAuth() {
        const username = store.authForm.username.trim();
        const password = store.authForm.password;
        if (!username || !password) {
            store.authError = 'Podaj nazwę użytkownika i hasło.';
            return;
        }
        store.authLoading = true;
        store.authError = '';
        try {
            const endpoint = store.authMode === 'register' ? '/api/auth/register' : '/api/auth/login';
            const res = await fetch(endpoint, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username, password}),
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                store.authError = data.error || 'Wystąpił błąd.';
                return;
            }
            store.currentUser = data.user;
            store.showAuthModal = false;
        } catch (e) {
            store.authError = 'Nie udało się połączyć z serwerem.';
        } finally {
            store.authLoading = false;
        }
    }

    async function logout() {
        try {
            await fetch('/api/auth/logout', {method: 'POST'});
        } catch (e) {
        }
        store.currentUser = null;
    }

    Object.assign(F, {checkSession, openAuthModal, submitAuth, logout});
})();
