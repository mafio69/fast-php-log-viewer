# fast-php-log-viewer

> Standalone log viewer for [fast-php-logger](https://github.com/mafio69/fast-php-logger).
> Part of the **fast-php-\*** suite.

---

## Installation — without Composer (drop-in)

Best for: legacy projects, quick debugging, "just copy one file".

**Step 1.** Download the single file:

```sh
curl -o log-viewer.php https://raw.githubusercontent.com/mafio69/fast-php-log-viewer/main/dist/fast-php-log-viewer.php
```

Or copy manually from this repo:

```sh
cp dist/fast-php-log-viewer.php /your/project/log-viewer.php
```

**Step 2.** Open in browser — done.

By default it looks for `./logs/` next to the file. If your logs are elsewhere, add one line at the top:

```php
<?php
define('LOG_DIR', '/var/www/html/logs');
// rest of the file follows automatically
```

Or set an environment variable (no file edit needed):

```sh
# Apache / Nginx — in vhost config:
SetEnv LOG_DIR /var/www/html/logs

# PHP built-in server:
LOG_DIR=/var/www/html/logs php -S localhost:8080 log-viewer.php
```

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

## Usage in Docker

```sh
docker compose up -d
```

Open `http://localhost:9123` — no setup needed.

The container mounts `/var/log` to `/host/var/log` and serves logs on port 9123.

---

## Features

- Lists log files sorted by date (newest first)
- Parses `fast-php-logger` format: `[datetime] [LEVEL] [file:line] message {context}`
- Filter by log level (DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY)
- Full-text search across message and location
- Expandable JSON context per entry
- Color-coded levels with retro terminal UI (CRT style)
- Vue 3 + Tailwind CSS — no build step, no node_modules
- **Directory selector** — choose from 4 default directories (docker, host, home, repository) or add your own
- **SSH support** — browse and read log files on remote servers via SSH
- **Setup wizard** — first-run configuration wizard for encryption keys, SSH, and directories
- **Bookmarks** — bookmark important log entries for quick access
- **Pagination** — large log files are paginated with sortable columns

## Log format

```
[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Something off {"user":"jan@example.com"}
```

Compatible with all `fast-php-logger` directory structures:

| `dateStructure` | Path |
|---|---|
| `Y/m` (default) | `logs/2026/05/2026-05-03.log` |
| `Y` | `logs/2026/2026-05-03.log` |
| `Y/m/d` | `logs/2026/05/03/2026-05-03.log` |
| `""` (flat) | `logs/2026-05-03.log` |

## API Endpoints

All API routes are prefixed with `/api/`. Legacy `?action=X` parameters are still supported via backward compatibility.

| Endpoint                       | Method | Description                 |
|--------------------------------|--------|-----------------------------|
| `/api/setup/status`            | GET    | Check setup wizard status   |
| `/api/setup/step`              | POST   | Execute setup step          |
| `/api/setup/migrate-ssh`       | POST   | Migrate SSH config          |
| `/api/app-config`              | GET    | Get application config      |
| `/api/app-config`              | POST   | Update application config   |
| `/api/directories`             | GET    | List configured directories |
| `/api/files`                   | GET    | List log files              |
| `/api/entries`                 | GET    | Get log entries             |
| `/api/config/directories`      | POST   | Add allowed directory       |
| `/api/config/directories/{id}` | PUT    | Update directory config     |
| `/api/config/directories/{id}` | DELETE | Remove directory config     |
| `/api/scan/directories`        | GET    | Scan for directories        |
| `/api/ssh/test-connection`     | POST   | Test SSH connection         |
| `/api/ssh/list-files`          | POST   | List files via SSH          |
| `/api/ssh/read-file`           | POST   | Read file via SSH           |
| `/api/ssh/download-file`       | POST   | Download file via SSH       |

## Structure

```
fast-php-log-viewer/
├── public/
│   ├── index.php          ← Entry point (Slim + Vue UI)
│   ├── css/style.css      ← Styles
│   └── js/app.js          ← Vue 3 application
├── src/
│   ├── Bootstrap/
│   │   ├── AppBootstrap.php  ← App factory
│   │   ├── app.php           ← Slim app setup
│   │   ├── container.php     ← DI container definitions
│   │   └── routes.php        ← Route definitions
│   ├── Config/
│   │   ├── ConfigManager.php ← App configuration manager
│   │   └── LogConfig.php     ← Log-specific configuration
│   ├── Controller/
│   │   ├── AppConfigController.php
│   │   ├── DirectoryController.php
│   │   ├── LogController.php
│   │   ├── SetupController.php
│   │   └── SSHController.php
│   ├── Middleware/
│   │   └── SetupMiddleware.php
│   ├── Repository/
│   │   ├── LogEntry.php
│   │   ├── LogFile.php
│   │   └── SSHConnection.php
│   ├── Routing/
│   │   └── LegacyRouter.php ← Backward compat for ?action=X
│   └── Service/
│       ├── GlobLogFinder.php
│       ├── LogFinder.php
│       ├── LogFinderInterface.php
│       ├── LogParser.php
│       ├── LogScanner.php
│       ├── RemoteLogFinder.php
│       ├── SecurityService.php
│       ├── SetupWizard.php
│       └── SSH.php
├── dist/
│   └── fast-php-log-viewer.php  ← Single-file drop-in (no Composer)
├── example/
│   └── viewer.php           ← Ready-to-use Composer entry point
├── tests/
│   ├── Config/
│   ├── Controller/
│   ├── Middleware/
│   ├── Routing/
│   ├── Service/
│   ├── LogParserTest.php
│   ├── LogFinderTest.php
│   └── LogScannerTest.php
├── data/                    ← App data (config, database)
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── start.sh
├── docker-compose.yml
├── index.php                ← Legacy entry point (redirects to public/)
└── composer.json
```

## Requirements

- PHP >= 8.1
- `ext-pdo`, `ext-pdo_sqlite`, `ext-ssh2`
- Composer (for full installation)

## fast-php-\* suite

| Package | Description |
|---|---|
| [fast-php-logger](https://github.com/mafio69/fast-php-logger) | PSR-3 file logger |
| [fast-php-log-viewer](https://github.com/mafio69/fast-php-log-viewer) | Log viewer (this package) |
| [docker-fast-logger](https://github.com/mafio69/docker-fast-logger) | Docker dev environment with both pre-installed |

## License

MIT
