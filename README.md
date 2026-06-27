# fast-php-log-viewer

> Standalone log viewer for [fast-php-logger](https://github.com/mafio69/fast-php-logger).
> Part of the **fast-php-\*** suite.

---



## Installation — Composer

Best for: new projects, Docker environments, when you already use Composer.

```sh
composer require mafio69/log-viewer
```

Create a single entry point file (e.g. `log-viewer.php` in your webroot):

```php
<?php
define('LOG_DIR', __DIR__ . '/logs');
require_once __DIR__ . '/vendor/autoload.php';

$app = \Mariusz\LogViewer\Bootstrap\AppBootstrap::create();
$request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
$app->run($request);
```

A ready-to-use example file is included at [`example/viewer.php`](example/viewer.php).

---

## Docker

### Quick start

```sh
docker compose up -d
```

Open `http://localhost:9123` — no setup needed.

### Volume mounts

| Host path              | Container path         | Purpose                                  |
|------------------------|------------------------|------------------------------------------|
| `./docker-logs/nginx/` | `/var/log/nginx/`      | Sample nginx logs                        |
| `/var/log`             | `/host/var/log`        | Host system logs                         |
| `~/logs`               | `/host/home/logs`      | User home logs                           |
| `~/.ssh`               | `/home/www-data/.ssh`  | SSH keys (for remote log access)         |
| `/var/run/docker.sock` | `/var/run/docker.sock` | Docker socket (for reading other container logs) |

### Trzy tryby dostepu do plikow logow

Aplikacja obsluguje trzy tryby dostepu do logow, wybierane w panelu bocznym:

| Tryb | Opis | Przyklad |
|---|---|---|
| **DOCKER** | Logi wewnatrz kontenera aplikacji (zlycza wolumenow) | `/var/log/nginx/error.log` |
| **HOST** | Logi hosta montowane przez `/host/var/log` | `/host/var/log/nginx/error.log` |
| **KONTENER** | Logi z innego kontenera Docker przez `docker exec cat` | podaj `container_name` + `/var/log/nginx/error.log` |

### Czytanie logow z innego kontenera

1. W panelu bocznym wpisz nazwe kontenera (lub container ID) w pole `container_name`
2. Wpisz sciezke do pliku wewnatrz tego kontenera, np. `/var/log/nginx/error.log`
3. Kliknij **ZAŁADUJ**

Aplikacja uzywa Docker Engine API przez socket `/var/run/docker.sock` do wykonania
`docker exec cat <sciezka>` wewnatrz wskazanego kontenera.

**Wymagania:**
- `/var/run/docker.sock` musi byc dostepny i montowany (domyslnie w docker-compose)
- PHP process (www-data) musi miec uprawnienia do socketu (start.sh konfiguruje automatycznie)

### Uruchomienie bez Docker Compose

```sh
docker run -d \
  -p 9123:80 \
  -v .:/var/www/html \
  -v ./logs:/var/www/html/logs \
  -v /var/log:/host/var/log \
  -v /var/run/docker.sock:/var/run/docker.sock \
  fast-php-log-viewer
```

---

## SSH — logi zdalne

Aplikacja wspiera przegladanie i czytanie logow na zdalnych serwerach przez SSH.

### Tryby autentykacji

- **Haslo** — autentykacja haslem uzytkownika
- **Klucz SSH** — autentykacja kluczem prywatnym (RSA, Ed25519, ECDSA) + opcjonalne haslo klucza

### Przeplyw dzialania

1. Uzytkownik konfiguruje polaczenie SSH (host, user, auth)
2. Frontend wysyla `POST /api/ssh/list-files` z danymi polaczenia
3. Backend nawiazuje polaczenie SSH (przez `ext-ssh2`), przeszukuje zdalny katalog
4. Pliki sa pobierane i cache'owane lokalnie w `data/` dla szybkiego dostepu
5. Logi zdalne sa wyswietlane tak samo jak lokalne

### Uwagi bezpieczenstwa

- Hasla SSH NIGDY nie sa zapisywane w plikach konfiguracyjnych ani bazie danych
- Profile SSH sa przechowywane w `data/app_config.json` (0600) bez hasel
- Hasla sa przekazywane tylko w pamieci podczas aktywnej sesji

---

## Features

- Listy plikow logow posortowane po dacie (najnowsze pierwsze)
- Parsowanie formatu `fast-php-logger`: `[datetime] [LEVEL] [file:line] message {context}`
- Filtrowanie po poziomie logowania (DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY)
- Wyszukiwanie full-text po wiadomosci i lokalizacji
- Rozwijany kontekst JSON dla kazdego wpisu
- Kolorowane poziomy w stylu retro terminala (CRT)
- Vue 3 + Tailwind CSS — bez build step, bez node_modules
- **Wybór katalogu** — 4 domyslne katalogi (docker, host, home, repository) + wlasne
- **Bezposrednia sciezka** — szybki dostep do dowolnego pliku przez wpisanie sciezki
- **Docker container reader** — czytanie logow z innych kontenerow przez Docker API
- **SSH** — przegladanie i czytanie logow na zdalnych serwerach
- **Setup wizard** — konfiguracja pierwszego uruchomienia (klucze, SSH, katalogi)
- **Bookmarks** — zakladki do waznych wpisow logow
- **Paginacja** — duze pliki logow z sortowalnymi kolumnami

---

## Log format

```
[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Something off {"user":"jan@example.com"}
```

Also supports nginx error log, nginx access log, syslog, PHP errors, and custom formats.

Compatible with all `fast-php-logger` directory structures:

| `dateStructure` | Path |
|---|---|
| `Y/m` (default) | `logs/2026/05/2026-05-03.log` |
| `Y` | `logs/2026/2026-05-03.log` |
| `Y/m/d` | `logs/2026/05/03/2026-05-03.log` |
| `""` (flat) | `logs/2026-05-03.log` |

---

## API Endpoints

| Endpoint                       | Method | Description                          |
|--------------------------------|--------|--------------------------------------|
| `/api/setup/status`            | GET    | Check setup wizard status            |
| `/api/setup/step`              | POST   | Execute setup step                   |
| `/api/setup/migrate-ssh`       | POST   | Migrate SSH config from localStorage |
| `/api/app-config`              | GET    | Get application config               |
| `/api/app-config`              | POST   | Update application config            |
| `/api/config/default-directories` | GET | Get built-in default directories   |
| `/api/directories`             | GET    | List configured directories          |
| `/api/files`                   | GET    | List log files in directory          |
| `/api/entries`                 | GET    | Get log entries from file            |
| `/api/config/directories`      | POST   | Add allowed directory                |
| `/api/config/directories/{id}` | PUT    | Update directory config              |
| `/api/config/directories/{id}` | DELETE | Remove directory config              |
| `/api/scan/directories`        | GET    | Scan filesystem for log directories  |
| `/api/ssh/test-connection`     | POST   | Test SSH connection                  |
| `/api/ssh/list-files`          | POST   | List files via SSH                   |
| `/api/ssh/read-file`           | POST   | Read file via SSH                    |
| `/api/ssh/download-file`       | POST   | Download file via SSH to local cache |

### Kluczowe parametry `/api/entries`

| Parametr        | Opis                                                       |
|-----------------|------------------------------------------------------------|
| `file`          | Sciezka do pliku logow (wymagane)                          |
| `dir`           | Klucz katalogu kontekstowego (dla walidacji dostepu)       |
| `container_id`  | ID/nazwa kontenera Docker do czytania przez Docker API    |
| `level`         | Filtruj po poziomie logowania                              |

Jesli `container_id` jest ustawiony, `file` jest sciezka WEWNATRZ kontenera (np. `/var/log/nginx/error.log`).

---

## Structure

```
fast-php-log-viewer/
├── public/
│   ├── index.php              ← Entry point (Slim + Vue SPA)
│   ├── css/style.css
│   └── js/
│       ├── app.js             ← Vue 3 bootstrap
│       ├── store.js           ← Reactive state + API layer
│       └── components/
│           ├── VApp.js        ← Root component
│           ├── Sidebar.js     ← Dir/file selector + direct path
│           ├── DataTable.js   ← Log entries table
│           ├── Toolbar.js     ← Filters, search, bookmarks
│           ├── SSHModal.js    ← SSH connection manager
│           └── SetupWizard.js ← First-run wizard
├── src/
│   ├── Bootstrap/             ← Slim app factory, DI, routes
│   ├── Config/
│   │   ├── ConfigManager.php  ← JSON app config I/O
│   │   └── LogConfig.php      ← SQLite directory config
│   ├── Controller/
│   │   ├── LogController.php  ← Core: dirs, files, entries
│   │   ├── DirectoryController.php
│   │   ├── SSHController.php
│   │   ├── SetupController.php
│   │   └── AppConfigController.php
│   ├── Middleware/
│   │   └── SetupMiddleware.php ← Blocks API before setup done
│   ├── Repository/
│   │   ├── LogEntry.php
│   │   ├── LogFile.php
│   │   └── SSHConnection.php
│   ├── Routing/
│   │   └── LegacyRouter.php   ← ?action= → /api/* compat
│   └── Service/
│       ├── DockerExecService.php   ← Docker socket API client
│       ├── LogParser.php           ← Multi-format log parser
│       ├── PathResolver.php        ← Path resolution (~/, rel, colon)
│       ├── FileAccessValidator.php ← Path allowlisting
│       ├── GlobLogFinder.php       ← Local file finder
│       ├── RemoteLogFinder.php     ← SSH remote file finder
│       ├── SSH.php                 ← SSH connection (ext-ssh2)
│       ├── LogScanner.php          ← Filesystem scanner
│       ├── SecurityService.php     ← Input sanitization
│       └── SetupWizard.php         ← Setup wizard logic
├── docs/
│   ├── design.md              ← Architecture design (PL)
│   ├── requirements.md         ← Feature requirements (PL)
│   └── technical.md            ← Technical details (PL)
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── php-errors.ini
│   └── start.sh
├── templates/
│   └── viewer.php              ← SPA HTML shell
├── data/                       ← App data (config, DB, backups)
├── tests/                      ← PHPUnit tests + property-based
├── docker-compose.yml
└── composer.json
```

---

## Requirements

- PHP >= 8.1
- `ext-pdo`, `ext-pdo_sqlite`
- `ext-ssh2` (for SSH remote log support)
- `ext-json`
- Composer
- Docker socket (for Docker container log reading)

---

## fast-php-\* suite

| Package | Description |
|---|---|
| [fast-php-logger](https://github.com/mafio69/fast-php-logger) | PSR-3 file logger |
| [fast-php-log-viewer](https://github.com/mafio69/fast-php-log-viewer) | Log viewer (this package) |
| [docker-fast-logger](https://github.com/mafio69/docker-fast-logger) | Docker dev environment with both pre-installed |

---

## License

MIT
