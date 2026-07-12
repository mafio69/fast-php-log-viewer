# Dokumentacja techniczna — fast-php-log-viewer

> Opis architektury, przeplywow danych, integracji SSH i Docker.

---

## 1. Architektura ogolna

### Stos technologiczny

| Warstwa | Technologia |
|---|---|
| Frontend | Vue 3 (CDN, reactive), Tailwind CSS (CDN), vanilla JS |
| Backend | PHP 8.4, Slim Framework 4, PHP-DI 7 |
| Baza danych | SQLite (przez PDO) |
| Komunikacja | REST API (JSON), PSR-7 (Slim) |
| SSH | ext-ssh2 |
| Docker | Unix socket `/var/run/docker.sock`, Docker Engine API v1.47 |

### Struktura katalogow (src/)

```
src/
├── Bootstrap/
│   ├── AppBootstrap.php    → fabryka Slim\App (tworzy instancje aplikacji)
│   ├── app.php             → konfiguracja Slim (middleware, routing)
│   ├── container.php       → definicje kontenera DI (PHP-DI)
│   ├── routes.php          → definicje tras REST
│   └── frontend.php        → punkt startowy (API vs SPA)
├── Config/
│   ├── ConfigManager.php   → odczyt/zapis data/app_config.json
│   └── LogConfig.php       → SQLite — konfiguracja katalogow
├── Controller/
│   ├── LogController.php           → /api/directories, files, entries
│   ├── DirectoryController.php     → /api/config/directories
│   ├── SSHController.php           → /api/ssh/*
│   ├── SetupController.php         → /api/setup/*
│   └── AppConfigController.php     → /api/app-config
├── Middleware/
│   └── SetupMiddleware.php         → blokuje /api/* przed ukonczeniem setupu
├── Routing/
│   └── LegacyRouter.php            → mapa ?action=X → /api/*
├── Service/
│   ├── DockerExecService.php       → komunikacja z Docker API
│   ├── LogParser.php               → parser formatow logow
│   ├── PathResolver.php            → rozwiazywanie sciezek (~/, relative, prefix:path)
│   ├── FileAccessValidator.php     → walidacja dostepu do plikow
│   ├── GlobLogFinder.php           → wyszukiwanie plikow lokalnie (glob)
│   ├── RemoteLogFinder.php         → wyszukiwanie plikow przez SSH
│   ├── SSH.php                     → obsluga polaczenia SSH
│   ├── LogFinderInterface.php      → interfejs dla finderow
│   ├── LogFinder.php               → legacy finder
│   ├── LogScanner.php              → skanowanie systemu plikow
│   ├── SecurityService.php         → sanitacja wejscia
│   └── SetupWizard.php             → logika wizarda konfiguracji
└── Repository/
    ├── LogEntry.php
    ├── LogFile.php
    └── SSHConnection.php
```

### Cykl zycia zadania HTTP

```
Przegladarka → GET /api/entries?file=...&container_id=...
  │
  ├─ public/index.php
  │     └─ frontend.php → wykrywa /api/* → laduje Slim
  │
  ├─ Slim routing → SetupMiddleware.process()
  │     ├─ Sprawdza: czy /api/entries jest chronione? TAK
  │     ├─ Sprawdza: czy setup_complete = true?
  │     │   ├─ NIE → HTTP 503 "setup_required"
  │     │   └─ TAK → przekazuje dalej
  │
  ├─ LogController.getEntries($request, $response)
  │     ├─ Pobiera 'file' z query params
  │     ├─ Pobiera 'container_id' z query params
  │     │
  │     ├─ Jesli container_id ustawione:
  │     │     └─ getEntriesFromContainer()
  │     │           ├─ DockerExecService.readFile(containerId, filePath)
  │     │           │     ├─ validateContainerId() → regex /^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/
  │     │           │     ├─ validateFilePath() → musi zaczynac sie od /
  │     │           │     ├─ createExec() → POST /v1.47/containers/{id}/exec
  │     │           │     ├─ startExec() → POST /v1.47/exec/{id}/start
  │     │           │     └─ demuxStream() → parsuje multiplexowany strumien
  │     │           └─ LogParser.parseString($content)
  │     │
  │     └─ Jesli brak container_id:
  │           ├─ FileAccessValidator.isFileAllowed()
  │           ├─ file_exists() → sprawdzenie lokalne
  │           └─ LogParser.parseFile($filePath)
  │
  └─ Response JSON → przegladarka
```

---

## 2. Przeplywy danych

### 2.1. Tryb lokalny (DOCKER / HOST)

Scenariusz: uzytkownik wybiera tryb DOCKER, wpisuje `/var/log/nginx/error.log`

```
Sidebar.vue → store.directFileMode = 'docker'
             → store.directFilePath = '/var/log/nginx/error.log'
             → klikniecie "ZALADUJ" → emit('load-direct-file')

store.js → loadDirectFile()
  ├─ resolvedPath = '/var/log/nginx/error.log'  (tryb docker: sciezka bez prefiksu)
  ├─ URL: /api/entries?file=%2Fvar%2Flog%2Fnginx%2Ferror.log
  └─ fetchJson(url) → store.entries

LogController.getEntries()
  ├─ container_id = null → pomija sciezke Docker
  ├─ dirKey = null → brak kontekstu katalogu
  ├─ FileAccessValidator.isFileAllowed('/var/log/nginx/error.log', null)
  │     ├─ sprawdza wszystkie zarejestrowane katalogi w SQLite
  │     ├─ jesli brak → autoRegisterParentDir('/var/log/nginx')
  │     │     └─ dodaje do dozwolonych (chyba ze /etc, /root, /proc...)
  │     └─ ponowna walidacja
  ├─ file_exists('/var/log/nginx/error.log') → TRUE (plik jest w kontenerze)
  └─ LogParser.parseFile() → zwraca sparsowane wpisy
```

**Scenariusz HOST:**

```
Sidebar → directFileMode = 'host'
store.js → resolvedPath = '/host' + '/var/log/nginx/error.log'
         → /host/var/log/nginx/error.log
         → ten plik istnieje dzieki mount /var/log:/host/var/log
```

### 2.2. Tryb kontenera Docker (container_id)

Scenariusz: uzytkownik chce czytac logi z INNEGO kontenera

```
Sidebar.vue → store.containerId = 'my-nginx-container'
             → store.directFilePath = '/var/log/nginx/error.log'
             → DOCKER/HOST buttons ukryte (v-if="!store.containerId")
             → wyswietla "czytanie z kontenera"
             → klikniecie "ZALADUJ" → emit('load-direct-file')

store.js → loadDirectFile()
  ├─ containerId = 'my-nginx-container'
  ├─ resolvedPath = '/var/log/nginx/error.log'  (sciezka wewnatrz kontenera)
  ├─ URL: /api/entries?file=...&container_id=my-nginx-container
  └─ fetchJson(url)

LogController.getEntries()
  ├─ container_id = 'my-nginx-container' → NIE null
  └─ getEntriesFromContainer('my-nginx-container', '/var/log/nginx/error.log')
        │
        ├─ DockerExecService.readFile(containerId, filePath)
        │     ├─ Sprawdza: /var/run/docker.sock istnieje?
        │     ├─ validateContainerId('my-nginx-container') → regex OK
        │     ├─ validateFilePath('/var/log/nginx/error.log') → zaczyna sie od / OK
        │     ├─ createExec()
        │     │     └─ POST unix:///var/run/docker.sock
        │     │           /v1.47/containers/my-nginx-container/exec
        │     │           Body: {"AttachStdout":true,"AttachStderr":true,
        │     │                  "Cmd":["cat","/var/log/nginx/error.log"]}
        │     │           Response: {"Id":"exec_abc123..."}
        │     │
        │     └─ startExec('exec_abc123...')
        │           └─ POST /v1.47/exec/exec_abc123.../start
        │                 Body: {"Detach":false,"Tty":false}
        │                 Response: [multiplexed stream] → demuxStream()
        │                   ├─ Stream type 1 (stdout): zawartosc pliku
        │                   └─ Stream type 2 (stderr): bledy (np. No such file)
        │
        └─ LogParser.parseString($content) → parsuje jak lokalny plik
```

### 2.3. Tryb SSH

```
SSHModal.vue → uzupelnienie formularza (host, user, auth)
             → executeSSHConnection()

store.js → executeSSHConnection()
  └─ POST /api/ssh/list-files
        Body: {ssh_host, ssh_user, ssh_port, ssh_auth_method, ssh_password, ...}

SSHController.listFiles()
  ├─ Tworzy instancje SSH z configu z request body
  ├─ $ssh->connect() → ssh2_connect() + auth
  ├─ Tworzy RemoteLogFinder($ssh)
  ├─ $finder->findAll($remotePath)
  │     └─ SSH exec: find /path -name "*.log" -maxdepth 3
  └─ Zwraca liste plikow

Dla czytania pliku:
  POST /api/ssh/download-file → pobiera zawartosc, zapisuje lokalnie w data/
  POST /api/ssh/read-file → zwraca zawartosc jako JSON text
```

### 2.4. Przegladanie katalogow (directory browsing)

```
Sidebar → wybor katalogu z dropdown → emit('change-dir')

store.js → loadFiles()
  ├─ /api/files?path=... lub ?dir=...
  │
  └─ LogController.getFiles()
        ├─ path → resolvePath + GlobLogFinder.findAll()
        ├─ dir → szuka w SQLite po nazwie + GlobLogFinder.findAll()
        └─ zwraca [{file, date, size}, ...]

Po wyborze pliku → loadEntries()
  ├─ /api/entries?file=...&dir=...
  ├─ LogController.getEntries()
  │     ├─ FileAccessValidator.isFileAllowed()
  │     ├─ file_exists()
  │     └─ LogParser.parseFile()
  └─ applyFilters() → filtrowanie, sortowanie, paginacja
```

### 2.5. Klucze katalogow — format klucz:sciezka

Format `prefix:path` uzywany w kluczach katalogow:

| Klucz | Co to znaczy | Rozwiazywanie |
|---|---|---|
| `docker:/var/log` | Katalog /var/log wewnatrz kontenera | `PathResolver` wyciaga `path` po `:`, rozwija absolutnie |
| `host:/var/log` | /host/var/log (mount hosta) | j.w. |
| `host-home:~/logs` | ~/logs (mount hosta) | `realpath()` dla `~/` na `/root` lub `$_SERVER['HOME']` |
| `repository:logs` | logs/ (relatywna) | Dolacza `ROOT_DIR` |
| `ssh:connection-name` | Katalog zdalny SSH | Pomijane przez `PathResolver` (zwraca null), obslugiwane osobno |
| `/absolute/path` | Sciezka absolutna | Uzywana bezposrednio |

### 2.6. Walidacja dostepu do plikow

`FileAccessValidator.isFileAllowed(filePath, dirKey)`:

1. **SSH** — jesli `dirKey` zaczyna sie od `ssh:`, sprawdza tylko czy `realpath` nie jest false
2. **dirKey podany** — rozwiazuje sciezke katalogu (`PathResolver.resolveDirPath()`), sprawdza czy plik jest pod ta sciezka
3. **Fallback** — sprawdza wszystkie zarejestrowane katalogi w SQLite
4. **Auto-register** — jesli plik jest `/absolute/sciezka` bez dirKey i walidacja nie przeszla, `autoRegisterParentDir()` dodaje katalog nadrzedny do SQLite (z wyjatkiem `/etc`, `/root`, `/proc`, `/sys`, `/dev`)

---

## 3. Docker — szczegoly techniczne

### 3.1. Docker Compose (docker-compose.yml)

```yaml
services:
  php:
    build:
      context: .
      dockerfile: docker/Dockerfile
    ports:
      - "9123:80"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock   # Docker API
      - /var/log:/host/var/log                        # logi hosta
      - ~/logs:/host/home/logs                        # logi z home
      - ~/.ssh:/home/www-data/.ssh                    # klucze SSH
      - ./docker-logs/nginx:/var/log/nginx            # przykladowe logi
      - ./data:/var/www/html/data                     # dane aplikacji
```

### 3.2. Dockerfile

```
FROM mafio69/php-env:8.4-fpm-alpine
# ... PHP 8.4 + FPM + nginx
# COPY aplikacji
# composer install
# Uruchomienie przez start.sh
```

### 3.3. start.sh — konfiguracja uprawnien

```sh
# 1. Tworzy logi nginx z www-data
touch /var/log/nginx/access.log /var/log/nginx/error.log
chmod 666 /var/log/nginx/access.log /var/log/nginx/error.log

# 2. Dodaje www-data do grupy adm (dla dostepu do logow systemowych)
addgroup www-data adm

# 3. Ustawia uprawnienia .ssh
chown -R www-data:www-data /var/www/.ssh
chmod 700 /var/www/.ssh

# 4. KLUCZOWE: Dodaje www-data do grupy docker hosta
#    Odczytuje GID /var/run/docker.sock, tworzy grupe docker_host
#    i dodaje do niej www-data
DOCKER_GID=$(stat -c '%g' /var/run/docker.sock)
if [ "$DOCKER_GID" != "0" ]; then
    addgroup -g "$DOCKER_GID" docker_host
    addgroup www-data docker_host
fi

# 5. Startuje PHP-FPM + nginx
php-fpm -D && nginx -g 'daemon off;'
```

**Dzieki temu** PHP-FPM worker (www-data) ma uprawnienia do odczytu `/var/run/docker.sock` i moze wykonywac `docker exec` wewnatrz innych kontenerow.

### 3.4. DockerExecService — komunikacja z Docker API

```
Klasa: DockerExecService (src/Service/DockerExecService.php)

Socket:    unix:///var/run/docker.sock
API:       Docker Engine API v1.47

Metody:
  isAvailable()           → sprawdza file_exists(/var/run/docker.sock)
  readFile(containerId, filePath) → pelny przeplyw

Przeplyw readFile():
  1. validateContainerId(containerId)
       regex: /^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/
       → akceptuje nazwy i ID kontenerow

  2. validateFilePath(filePath)
       musi zaczynac sie od /
       nie moze zawierac \0, \n, \r (ochrona przed injection)

  3. createExec(containerId, filePath)
       POST /v1.47/containers/{id}/exec
       Cmd: ["cat", filePath]  → wykonuje 'cat' wewnatrz kontenera
       Response: {"Id": "exec_id_string"}

  4. startExec(execId)
       POST /v1.47/exec/{id}/start
       Detach: false, Tty: false
       Response: multiplexed stream (Docker protocol)

  5. demuxStream(raw)
       Parsuje 8-bajtowe naglowki:
         Bajt 0:     typ strumienia (1=stdout, 2=stderr)
         Bajty 1-3:  padding
         Bajty 4-7:  rozmiar (uint32 BE)
         Bajty 8+:   dane
       → laczy stdout i stderr w jeden ciag

UWAGA (znany bug): readFile() sprawdza if ($output === '') i rzuca
"file_not_found". Pusty plik logow (np. error.log bez bledow) daje
pusty output z cat, co powoduje falszywy komunikat bledu.
```

### 3.5. Bezpieczenstwo Docker

- `containerId` walidowany regex — blokuje path traversal
- `filePath` walidowany — musi byc absolutny, bez znakow specjalnych
- Socket Docker dostepny tylko wewnatrz kontenera
- Uprawnienia socketu: www-data dodany do grupy docker_host
- `Cmd: ["cat", filePath]` — `cat` nie wykonuje shell expansion, minimalizuje ryzyko injection

---

## 4. SSH — szczegoly techniczne

### 4.1. Klasa SSH (src/Service/SSH.php)

```php
class SSH {
    // Nawiazanie polaczenia
    connect(): bool → ssh2_connect() → auth

    // Autentykacja
    authenticateWithPassword() → ssh2_auth_password()
    authenticateWithKey()      → ssh2_auth_pubkey_file()
        ├─ probuje bez hasla
        └─ jesli nieudane, probuje z haslem klucza (passphrase)

    // Operacje
    exec(command): string       → ssh2_exec() + stream
    listFiles(path): array      → ls -la [path]
    readFile(path): string      → cat [path]
    fileExists(path): bool      → test -f [path]
    directoryExists(path): bool → test -d [path]
    fileSize(path): int         → stat -f%z / stat -c%s [path]
}
```

### 4.2. Klucze SSH

W Dockerze klucze SSH sa montowane z hosta:
```
~/.ssh → /home/www-data/.ssh
```

Znajdowanie domyslnych kluczy (`findDefaultKeyPath()`):
```
/home/www-data/.ssh/id_rsa
/home/www-data/.ssh/id_ed25519
/home/www-data/.ssh/id_ecdsa
/var/www/.ssh/id_rsa
...
```

### 4.3. RemoteLogFinder (src/Service/RemoteLogFinder.php)

Wyszukuje pliki logow na zdalnym serwerze:

1. Sprawdza czy katalog istnieje: `test -d [path]`
2. Wyszukuje pliki: `find [path] -maxdepth 3 -name "*.log"` itd.
3. Patterns: `*.log`, `*error*`, `*debug*`, `*access*`, `*.php`, `messages`, `syslog`, itd.
4. Dla `allFiles=true`: `find [path] -maxdepth 1 -type f`
5. Usuwa duplikaty, sortuje po nazwie

### 4.4. Bezpieczenstwo SSH

- **Hasla NIGDY nie sa zapisywane** — `ConfigManager.filterSensitiveFields()` usuwa `ssh_password`, `ssh_key_passphrase` przed zapisem
- **Profile SSH w app_config.json** — zawieraja tylko host, user, port, auth_method, key_path (bez hasel)
- **app_config.json ma 0600** — tylko wlasciciel ma dostep
- **Escapeshellarg** — wszystkie sciezki i polecenia SSH uzywaja `escapeshellarg()`
- **Hasla tylko w pamieci** — przesylane w body POST, nigdy nie persistowane
- **Sanityzacja odpowiedzi** — `SecurityService::sanitizeOutput()` sprawdza czy odpowiedz nie zawiera binarnych danych

### 4.5. SSH Controller — endpointy

| Endpoint | Metoda | Opis |
|---|---|---|
| `/api/ssh/test-connection` | POST | Proba polaczenia SSH (bez zapisu) |
| `/api/ssh/list-files`      | POST | Listowanie plikow na zdalnym serwerze |
| `/api/ssh/read-file`       | POST | Odczyt zawartosci pliku jako JSON |
| `/api/ssh/download-file`   | POST | Pobranie pliku ze zdalnego serwera → cache lokalny w data/ |

### 4.6. Cache'owanie plikow SSH

Gdy uzytkownik czyta plik zdalny przez SSH:
1. Pierwszy odczyt: `POST /api/ssh/download-file` → pobiera plik, zapisuje w `data/ssh-cache/`
2. Kolejne odczyty: plik jest juz lokalny → `loadEntries()` czyta przez `/api/entries?file=...`
3. Klucz `ssh:connection-name` w dropdownie — pliki pokazywane z cache

---

## 5. Parsowanie logow — LogParser

### 5.1. Obslugiwane formaty

`LogParser` (src/Service/LogParser.php) obsluguje wiele formatow automatycznie:

| Format | Przyklad | Wykrywanie |
|---|---|---|
| **fast-php-logger** | `[2026-05-03 14:25:00] [WARNING] [app/index.php:42] msg` | `[datetime] [LEVEL] [file:line]` |
| **Nginx error log** | `2026/05/03 14:25:00 [error] 12345#0: *1 msg, client: 1.2.3.4` | `YYYY/MM/DD HH:MM:SS [level]` |
| **Nginx access log** | `1.2.3.4 - - [03/May/2026:14:25:00 +0000] "GET /"` | `[DD/Mon/YYYY:HH:MM:SS` |
| **PHP error** | `[03-May-2026 14:25:00 UTC] PHP Fatal error: msg in file:line` | `PHP Fatal error:` lub `PHP Warning:` |
| **Syslog** | `May  3 14:25:00 hostname service[pid]: message` | `Mon DD HH:MM:SS hostname` |
| **Generic** | dowolny tekst z timestampem lub poziomem | fallback parser |

### 5.2. Struktura wpisu (entry)

Kazdy wpis ma format:
```json
{
    "datetime": "2026-05-03 14:25:00",
    "level": "WARNING",
    "message": "Something off",
    "location": "app/index.php:42",
    "context": {"user": "jan@example.com"},
    "raw": "[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Something off {\"user\":\"jan@example.com\"}"
}
```

### 5.3. Parsowanie kontekstu JSON

`LogParser` wykrywa obiekty JSON w wiadomosciach (nawet `{'key':'value'}` z pojedynczymi cudzyslowami).
Obsluguje zarowno `stdClass` jak i tablice asocjacyjne.

---

## 6. Frontend — szczegoly techniczne

### 6.1. Architektura Vue

```
public/js/
├── app.js                  → bootstrap Vue, komponenty, init
├── store.js                → reactive store (Vue.reactive) + API functions
└── components/
    ├── VApp.js             → root komponent (kordynacja eventow)
    ├── Sidebar.js          → wybor katalogu + direct file + container
    ├── DataTable.js        → tabela wpisow z paginacja
    ├── Toolbar.js          → filtry, wyszukiwarka, bookmarks
    ├── SSHModal.js         → zarzadzanie polaczeniami SSH
    └── SetupWizard.js      → wizard pierwszego uruchomienia
```

Brak build step — Vue 3 i Tailwind CSS sa ladowane z CDN (`cdn.jsdelivr.net`).

### 6.2. Store (store.js)

Reactive store zawiera caly stan aplikacji:
- `files`, `entries`, `filtered` — dane logow
- `selectedFile`, `selectedDir` — wybrany plik/katalog
- `containerId`, `directFilePath`, `directFileMode` — direct path + container
- `filterText`, `excludedLevels`, `sortOrder` — stan filtrow
- `bookmarks`, `sshConnections` — dane uzytkownika
- `tableSortColumn`, `tableSortDirection`, `tablePage`, `tablePageSize` — stan tabeli

### 6.3. Przeplyw direct file load

```
Sidebar
  ├─ containerId: v-model="store.containerId"
  ├─ directFilePath: v-model="store.directFilePath"
  └─ @click="$emit('load-direct-file')"

VApp: @load-direct-file="loadDirectFile"

loadDirectFile() w store.js:
  ├─ resolvedPath = directFileMode === 'host' ? '/host' + path : path
  ├─ url = /api/entries?file=... [+ &container_id=...]
  ├─ fetch(url)
  └─ applyFilters()
```

### 6.4. Paginacja — DataTable

```javascript
tableSortedData = computed → sortuje filtered po wybranej kolumnie
tableTotalPages  = computed → ceil(sorted.length / pageSize)
tablePaginatedData = computed → slice dla biezacej strony

Stronicowanie: 50, 100, 250, 500, 1000 wpisow na strone
```

---

## 7. Setup Wizard — konfiguracja pierwszego uruchomienia

### 7.1. Sekwencja krokow

```
1. generate_keys      → generowanie InstallationId (UUID v4) + EncryptionKey (64 hex)
                         opcjonalnie: uzytkownik moze pominac
                         konsekwencja: backup niebedzie szyfrowany

2. ssh_config         → konfiguracja polaczenia SSH
                         pola: host, user, port, auth_method, key_path
                         opcjonalnie: mozna pominac
                         konsekwencja: SSH wylaczone

3. local_directories  → sciezka do lokalnych logow
                         opcjonalnie: mozna pominac
                         konsekwencja: brak katalogow logow

4. finalize           → finalizuje setup
                         ustawia setup_complete = true
                         odblokowuje dostep do /api/directories, files, entries
```

### 7.2. SetupMiddleware

```php
class SetupMiddleware implements MiddlewareInterface {
    // Chronione trasy (blokowane przed setup_complete = true):
    const PROTECTED_ROUTES = [
        '/api/directories',
        '/api/files',
        '/api/entries',
    ];

    // Przy starcie frontend sprawdza /api/setup/status
    // Jesli setup_required = true → wyswietla SetupWizard zamiast normalnego UI
    // Sam wizard uzywa tras /api/setup/* ktore NIE sa chronione
}
```

### 7.3. Przechowywanie konfiguracji

`data/app_config.json`:
```json
{
    "installation_id":    "uuid-v4",
    "setup_complete":     true,
    "setup_state":        "complete",
    "encryption_key":     "64-char-hex",
    "backup_encryption_enabled": true,
    "ssh_enabled":        true,
    "ssh_profiles":       [{...}],
    "local_directories":  [{...}],
    "created_at":         "ISO8601",
    "updated_at":         "ISO8601"
}
```

Uprawnienia: `0600` (rw tylko wlasciciel).

---

## 8. Obsluga bledow

| Blad | Kod HTTP | Przyczyna |
|---|---|---|
| `setup_required` | 503 | Setup nieukonczony, chronione API zablokowane |
| `missing_file` | 400 | Brak parametru `file` |
| `missing_dir` | 400 | Brak parametru `dir` |
| `access_denied` | 403 | Plik poza dozwolonymi katalogami |
| `file_not_found` | 404 | Plik nie istnieje (lub jest pusty — znany bug w Docker path) |
| `container_not_found` | 404 | Kontener Docker nie istnieje |
| `docker_unavailable` | 503 | `/var/run/docker.sock` nie istnieje |
| `docker_exec_failed` | 500 | Docker API zwrocilo blad przy exec |
| `parse_error` | 500 | LogParser nie przetworzyl zawartosci |
| `directory_not_found` | 404 | Katalog nie znaleziony w SQLite |
| `server_error` | 500 | Nieobsluzony wyjatek |

---

## 9. Testy

```bash
# Wszystkie testy
vendor/bin/phpunit

# Testy property-based (eris)
vendor/bin/phpunit --filter PropertyTest

# Testy jednostkowe
vendor/bin/phpunit --filter "Test$"
```

Struktura testow odpowiada strukturze `src/`:
- `tests/Config/` — ConfigManager, LogConfig
- `tests/Controller/` — SetupController, SSHController
- `tests/Middleware/` — SetupMiddleware
- `tests/Routing/` — LegacyRouter
- `tests/Service/` — SetupWizard, LogParser, PathResolver

Testy property-based uzywaja biblioteki **eris** (`giorgiosironi/eris`) do generowania losowych danych i sprawdzania wlasciwosci uniwersalnych.

---

## 10. Znane problemy (Known Issues)

### 10.1. Docker Exec — pusty plik jako "file_not_found"

**Problem:** `DockerExecService.readFile()` (linia 28) rzuca `RuntimeException("file_not_found")`
gdy output z `cat` jest pusty. Pusty plik logow (np. `error.log` bez bledow) powoduje
falszywy komunikat bledu "Plik nie istnieje".

**Rozwiazanie:** Nalezy sprawdzac exit code wykonanego exec-a (przez `GET /exec/{id}/json`)
zamiast polegac na pustym outpucie. Pusty output przy exit code 0 = plik istnieje, jest pusty.

### 10.2. Brak obslugi timeoutow Docker API

`DockerExecService` ma timeout 10s na socket, ale nie ma logiki retry.
Dlugie cat na duzych plikach moze przekroczyc timeout.

### 10.3. Container ID validation regex

Regex `/^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/` wymaga ze ID zaczyna sie od alfanumerycznego znaku.
Container ID zwykle spelniaja ten warunek, ale nazwy kontenerow moge zawierac inne znaki (np. `_`
na poczatku). Docker akceptuje znaki `[a-zA-Z0-9][a-zA-Z0-9_.-]*` w nazwach.

---

## 11. Environment Variables

| Zmienna | Plik | Opis |
|---|---|---|
| `LOG_DIR` | `.env` / definicja PHP | Sciezka do katalogu logow |
| `EDITOR_URL` | `.env` / definicja PHP | URL edytora (phpstorm://open?file={file}&line={line}) |
| `BACKUP_ENCRYPTION_KEY` | `.env` | Klucz szyfrowania backupu (generowany przez wizard) |
| `GIT_ACCES_TOKEN` | `.env` / `.config` | GitHub token (do pobierania prywatnych pakietow Composer) |
