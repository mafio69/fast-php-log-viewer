/**
 * store-docker.js — Docker container file listing & allow-list management
 */
window.FPLV = window.FPLV || {};

(function () {
    const F = window.FPLV;
    const store = F.store;

    let containerCheckTimer = null;

    async function loadDirectDockerFiles(dirPath) {
        const containerId = store.containerId.trim();
        try {
            store.loading = true;
            let url = '/api/files?path=' + encodeURIComponent(dirPath);
            if (containerId) {
                url += '&container_id=' + encodeURIComponent(containerId);
            }
            const files = await F.fetchJson(url);

            store.selectedDir = '';
            store.selectedFileContainerId = containerId;
            store.files = files;
            if (files.length) {
                store.selectedFile = files[0].file;
                await F.loadEntries();
            } else {
                store.selectedFile = '';
                store.entries = [];
                store.filtered = [];
            }

            if (containerId) {
                await saveDirectoryShortcut({
                    name: containerId + ':' + dirPath,
                    path: dirPath,
                    type: 'docker',
                    container_id: containerId,
                });
            }
        } catch (e) {
            if (e.message.includes('container_not_found')) {
                alert('Kontener nie został znaleziony.'); console.error('Container not found:', containerId);
            } else if (e.message.includes('container_not_allowed')) {
                if (confirm(containerNotAllowedExplanation(containerId))) {
                    await allowContainerAndRetry(containerId, dirPath);
                }
            } else if (e.message.includes('path_not_allowed')) {
                if (confirm(pathNotAllowedExplanation(dirPath))) {
                    await allowPathAndRetry(dirPath);
                }
            } else if (e.message.includes('docker_unavailable')) {
                alert('Docker nie jest dostępny.');
            } else {
                alert('Nie udało się załadować katalogu.'); console.error(e);
            }
            console.error('Load direct docker directory error:', e);
        } finally {
            store.loading = false;
        }
    }

    function containerNotAllowedExplanation(containerId) {
        return 'Kontener "' + containerId + '" nie jest na liście dozwolonych.\n\n'
            + 'Dlaczego to pytanie: ta aplikacja ma dostęp do gniazda Dockera (docker.sock) i działa '
            + 'jako serwer WWW bez logowania. Bez tej listy dowolna złośliwa strona otwarta w tej samej '
            + 'przeglądarce mogłaby po cichu (CSRF, przez localhost) odczytać pliki z DOWOLNEGO kontenera '
            + 'na tym hoście - nie tylko z tego, który właśnie chcesz przejrzeć.\n\n'
            + 'Dodanie kontenera do listy to świadoma decyzja, że mu ufasz. Zostaje zapamiętana w aplikacji, '
            + 'nie trzeba tego powtarzać.\n\n'
            + 'Dodać "' + containerId + '" do dozwolonych i spróbować ponownie?';
    }

    async function allowContainerAndRetry(containerId, dirPath) {
        try {
            const res = await fetch('/api/config/allowed-containers', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({container_id: containerId})
            });
            const data = await res.json();
            if (!data.success) {
                alert('Nie udało się dodać kontenera.'); console.error(data);
                return;
            }
        } catch (e) {
            alert('Nie udało się dodać kontenera.'); console.error(e);
            return;
        }
        await loadDirectDockerFiles(dirPath);
    }

    function pathNotAllowedExplanation(dirPath) {
        return 'Ścieżka "' + dirPath + '" nie jest na liście dozwolonych ścieżek.\n\n'
            + 'Dlaczego to pytanie: dozwolony kontener to nie to samo co dozwolona ścieżka. Nawet w kontenerze, '
            + 'któremu ufasz, nieograniczony dostęp do dowolnej ścieżki pozwoliłby złośliwej stronie (przez ten sam '
            + 'CSRF/localhost co przy kontenerach) odczytać cokolwiek w nim jest - np. /etc/passwd czy klucze w '
            + '/root/.ssh - a nie tylko logi, po które faktycznie tu przyszedłeś.\n\n'
            + 'Dodanie tej ścieżki do listy to świadoma decyzja. Zostaje zapamiętana w aplikacji, nie trzeba tego '
            + 'powtarzać dla tego samego katalogu.\n\n'
            + 'Dodać "' + dirPath + '" do dozwolonych ścieżek i spróbować ponownie?';
    }

    async function allowPathAndRetry(dirPath) {
        try {
            const res = await fetch('/api/config/allowed-container-paths', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({path_prefix: dirPath})
            });
            const data = await res.json();
            if (!data.success) {
                alert('Nie udało się dodać ścieżki.'); console.error(data);
                return;
            }
        } catch (e) {
            alert('Nie udało się dodać ścieżki.'); console.error(e);
            return;
        }
        await loadDirectDockerFiles(dirPath);
    }

    function scheduleContainerCheck() {
        clearTimeout(containerCheckTimer);
        const containerId = store.containerId.trim();
        const path = store.directFilePath.trim();
        if (store.directFileMode !== 'docker' || !containerId || !path) {
            store.containerCheckStatus = '';
            return;
        }
        store.containerCheckStatus = 'checking';
        containerCheckTimer = setTimeout(checkContainerPath, 500);
    }

    async function checkContainerPath() {
        const containerId = store.containerId.trim();
        const path = store.directFilePath.trim();
        if (!containerId || !path) return;
        try {
            await F.fetchJson('/api/files?container_id=' + encodeURIComponent(containerId) + '&path=' + encodeURIComponent(path));
            store.containerCheckStatus = 'ok';
        } catch (e) {
            if (e.message.includes('container_not_found')) store.containerCheckStatus = 'not_found';
            else if (e.message.includes('container_not_allowed')) store.containerCheckStatus = 'not_allowed';
            else if (e.message.includes('path_not_allowed')) store.containerCheckStatus = 'path_not_allowed';
            else store.containerCheckStatus = 'error';
        }
    }

    async function saveDirectoryShortcut(config) {
        try {
            const res = await fetch('/api/config/directories', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(config)
            });
            const data = await res.json();
            if (data.success) {
                await F.loadDirectories();
            }
        } catch (e) {
            console.error('Failed to save directory shortcut:', e);
        }
    }

    Object.assign(F, {
        loadDirectDockerFiles, scheduleContainerCheck,
    });
})();
