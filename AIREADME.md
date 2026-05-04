# AI Project Context — fast-php-log-viewer

## What this project is

A standalone log viewer for [fast-php-logger](https://github.com/mafio69/fast-php-logger).
Part of the **fast-php-*** developer tools suite.

Two distribution modes:
- **Composer package** (`mafio69/fast-php-log-viewer`) — for projects already using Composer
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

## Key files

| File | Purpose |
|---|---|
| `src/LogParser.php` | Parses log lines → structured arrays |
| `src/LogFinder.php` | Discovers log files, normalizes paths |
| `src/api.php` | JSON API: `?action=files`, `?action=entries&file=path` |
| `index.php` | Vue 3 + Tailwind UI entry point (Composer mode) |
| `dist/fast-php-log-viewer.php` | Self-contained single file (no-Composer mode) |
| `example/viewer.php` | Ready-to-copy Composer entry point |

## Security

API path traversal protection: requested file path must resolve inside `LOG_DIR`.
Uses `realpath()` with fallback for Windows/WSL UNC paths, normalizes `\` → `/`.

## Windows / WSL compatibility

`LogFinder::normalizePath()` converts backslashes to forward slashes before `glob()` calls.
`realpath()` fallback in API prevents false `403` when PHP runs on Windows with WSL paths.

## Suite context

| Repo | Role |
|---|---|
| `mafio69/fast-php-logger` | PSR-3 logger, writes the log files this viewer reads |
| `mafio69/fast-php-log-viewer` | This project |
| `mafio69/docker-fast-logger` | Docker dev environment, uses both packages |

## Conventions

- All git commits, PR descriptions, issue titles, and code comments in **English**
- PHP: `declare(strict_types=1)`, PSR-4 autoloading under `Mariusz\LogViewer\`
- No JS frameworks beyond Vue 3 CDN, no build tooling
- `dist/` file is hand-maintained (not generated), keep in sync with `src/` changes
- Version in `composer.json` — bump manually, tag with `git tag vX.Y.Z` on release

## Dev workflow (docker)

`docker-fast-php-logger` mounts `../PhpstormProjects/fast-php-log-viewer/src` and `index.php`
directly into the container's vendor directory — no rebuild needed after editing src files.

```yaml
# docker-compose.yml volumes:
- ../PhpstormProjects/fast-php-log-viewer/src:/var/www/html/vendor/mafio69/fast-php-log-viewer/src
- ../PhpstormProjects/fast-php-log-viewer/index.php:/var/www/html/vendor/mafio69/fast-php-log-viewer/index.php
```

After editing `src/` or `index.php` just refresh the browser — no restart needed.
