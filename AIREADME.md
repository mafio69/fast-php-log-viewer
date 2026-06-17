# AI Project Context — fast-php-log-viewer

## What this project is

A standalone log viewer for [fast-php-logger](https://github.com/mafio69/fast-php-logger).
Part of the **fast-php-*** developer tools suite.

Two distribution modes:
- **Composer package** (`mafio69/log-viewer`) — for projects already using Composer
- **Single-file drop-in** (`dist/fast-php-log-viewer.php`) — copy one file, open in browser, done

## Tech stack

- PHP 8.1+ (no framework)
- Vue 3 via CDN (no build step, no node_modules)
- Tailwind CSS via CDN
- PHPUnit 11 for tests

## Log format parsed

```
[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Message {"key":"value"}
```

Regex pattern: `^\[(?P<datetime>[^\]]+)\] \[(?P<level>[^\]]+)\] \[(?P<location>[^\]]+)\] (?P<message>.+?)(?:\s+(?P<context>\{.+\}))?\s*$`

## Directory structure of log files

`fast-php-logger` writes to `logDir/{dateStructure}/{prefix}Y-m-d{suffix}.log`.
Default: `logs/2026/05/2026-05-03.log` (`Y/m` structure).
`LogFinder` supports all variants: `Y/m`, `Y`, `Y/m/d`, flat.

## Project structure

```
fast-php-log-viewer/
├── public/
│   └── index.php              ← Vue 3 + Tailwind UI entry point
├── src/
│   ├── Config/
│   │   └── LogConfig.php      ← SQLite persistence (directories, SSH, settings)
│   ├── Controller/
│   │   └── LogController.php  ← API routing (match-based): directories, files, entries, SSH, config
│   ├── Repository/
│   │   ├── DirectoryRepository.php
│   │   ├── LogEntry.php       ← Log entry model
│   │   ├── LogFile.php        ← Log file model
│   │   ├── LogRepository.php  ← Local log file operations
│   │   ├── SSHConnection.php  ← SSH connection model
│   │   └── SSHRepository.php  ← CRUD for SSH connections (SQLite)
│   └── Service/
│       ├── LogFinder.php      ← Discovers log files, normalizes paths
│       ├── LogParser.php      ← Parses log lines → structured arrays (FPL, simple, legacy, PHP-error, nginx, APK)
│       ├── LogScanner.php     ← Auto-scans common paths for log files
│       ├── RemoteLogFinder.php ← Discovers logs on remote hosts via SSH
│       └── SSH.php            ← SSH connection, file listing, file reading (ext-ssh2)
├── tests/
│   ├── LogFinderTest.php
│   ├── LogParserTest.php
│   ├── LogScannerTest.php
│   ├── RemoteLogFinderTest.php
│   └── SSHTest.php
├── dist/
│   └── fast-php-log-viewer.php ← Single-file drop-in (no-Composer mode)
├── data/
│   └── logviewer.db           ← SQLite database (directories, SSH connections, settings)
├── bin/
│   └── build.php              ← Build script for generating dist file
├── example/
│   └── viewer.php             ← Ready-to-copy Composer entry point
├── Dockerfile                 ← PHP 8.4-FPM Alpine + Nginx
├── docker-compose.yml         ← Service orchestration (PHP + MySQL)
├── start.sh                   ← Docker build & run helper
├── composer.json
├── phpunit.xml
├── phpstan.neon               ← Static analysis config (level 5)
└── .php-cs-fixer.php          ← Code style config (PSR-12)
```

## API endpoints (`LogController.php`)

| Action | Method | Description |
|---|---|---|
| `?action=directories` | GET | List configured log directories |
| `?action=files[&dir=key]` | GET | List log files in a directory |
| `?action=entries&file=path` | GET | Parsed entries from a log file |
| `?action=config-dirs` | GET | List directories from SQLite config |
| `?action=config-add-dir` | POST | Add a directory to config |
| `?action=config-update-dir` | POST | Update a directory in config |
| `?action=config-delete-dir` | POST | Delete a directory from config |
| `?action=config-cleanup-duplicates` | POST | Remove duplicate directories |
| `?action=config-init-defaults` | POST | Initialize default directories |
| `?action=scan-directories` | GET | Auto-scan common paths for logs |
| `?action=ssh-test-connection` | POST | Test SSH connection |
| `?action=ssh-list-files` | POST | List log files on remote host |
| `?action=ssh-read-file` | POST | Read a log file via SSH |

## Security

- API path traversal protection: requested file path must resolve inside `LOG_DIR` or configured directories
- Uses `realpath()` with fallback for Windows/WSL UNC paths, normalizes `\` → `/`
- SSH credentials not persisted in localStorage (password entered on connect)

## Windows / WSL compatibility

`LogFinder::normalizePath()` converts backslashes to forward slashes before `glob()` calls.
`realpath()` fallback in API prevents false `403` when PHP runs on Windows with WSL paths.

## Suite context

| Repo | Role |
|---|---|
| `mafio69/fast-php-logger` | PSR-3 logger, writes the log files this viewer reads |
| `mafio69/log-viewer` | This project (Composer package name) |
| `mafio69/docker-fast-php-logger` | Docker dev environment, uses both packages |

## Conventions

- All git commits, PR descriptions, issue titles, and code comments in **English**
- PHP: `declare(strict_types=1)`, PSR-4 autoloading under `Mariusz\LogViewer\`
- No JS frameworks beyond Vue 3 CDN, no build tooling, no node_modules
- `dist/` file is hand-maintained (not generated), keep in sync with `src/` changes
- Version in `composer.json` — bump manually, tag with `git tag vX.Y.Z` on release
- Code style: PSR-12 via `php-cs-fixer` (`vendor/bin/php-cs-fixer fix`)
- Static analysis: PHPStan level 5 (`vendor/bin/phpstan analyse`)
- Tests: PHPUnit 11 (`vendor/bin/phpunit`)

## Dev workflow (docker)

`docker-fast-php-logger` mounts `../PhpstormProjects/fast-php-log-viewer/src` and `public/index.php`
directly into the container's vendor directory — no rebuild needed after editing src files.

```yaml
# docker-compose.yml volumes:
- ../PhpstormProjects/fast-php-log-viewer/src:/var/www/html/vendor/mafio69/log-viewer/src
- ../PhpstormProjects/fast-php-log-viewer/public/index.php:/var/www/html/vendor/mafio69/log-viewer/index.php
```

After editing `src/` or `public/index.php` just refresh the browser — no restart needed.

## Dev tools

```sh
# Run tests
vendor/bin/phpunit

# Fix code style (PSR-12)
vendor/bin/php-cs-fixer fix

# Static analysis
vendor/bin/phpstan analyse

# Docker: build & start on port 8080
./start.sh
# or manually:
docker compose up --build -d
```
