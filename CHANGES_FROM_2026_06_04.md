# Changes from 2026-06-04 10:00

## Overview

Major release (`v1.0.4` → `v2.0.0`) introducing SSH remote log viewing, SQLite
persistence, auto-scanning, CRT theme, DataTable integration, new log format
parsers, and full project restructuring into Config / Controller / Repository /
Service layers.

---

## Breaking changes

| Before | After |
|--------|-------|
| `mafio69/fast-php-log-viewer` | `mafio69/log-viewer` (Composer package name) |
| `src/LogParser.php`, `src/LogFinder.php` | `src/Service/LogParser.php`, `src/Service/LogFinder.php` |
| `src/api.php` (flat routing) | `src/Controller/LogController.php` (match-based routing) |
| No extensions required | `ext-pdo`, `ext-pdo_sqlite`, `ext-ssh2` now required |

---

## New features

### SSH remote log viewing
- `src/Service/SSH.php` – connect, list files, read contents via `ext-ssh2`
- `src/Service/RemoteLogFinder.php` – discover logs on remote hosts
- `src/Repository/SSHConnection.php` – connection model (host, port, user, key/password)
- `src/Repository/SSHRepository.php` – CRUD for SSH connections backed by SQLite
- UI: SSH modal in sidebar — test, add, delete connections; browse remote files

### SQLite configuration persistence
- `src/Config/LogConfig.php` – schema init (`log_directories`, `ssh_connections`,
  `settings`), directory / SSH CRUD
- `data/logviewer.db` – default database location
- Directories, SSH connections, and settings survive container restarts

### Auto-scanning (`LogScanner`)
- `src/Service/LogScanner.php` – scans common paths (`/var/log`, `./logs`, …)
  for files matching log extensions / glob patterns
- Exposed via `?action=scan` API endpoint

### CRT theme
- Green-on-black monospace palette (`Courier New`)
- Custom scrollbar, glow effects, color-coded level dots

### DataTable integration
- jQuery DataTables 1.13.7 added to `index.php`
- Sortable / searchable log entry table

### New log format parsers (`LogParser`)
- **nginx error log**: `2026/06/05 07:00:00 [error] 1234#0: *12345 message`
- **Alpine APK log**: `Running \`apk …\`` / `(N/M) Installing …` / `WARNING:` / `OK:`

---

## Architecture refactoring

```
src/
├── Config/
│   └── LogConfig.php          # SQLite persistence layer
├── Controller/
│   └── LogController.php      # API routing (match-based)
├── Repository/
│   ├── DirectoryRepository.php
│   ├── LogEntry.php
│   ├── LogFile.php
│   ├── LogRepository.php
│   ├── SSHConnection.php
│   └── SSHRepository.php
├── Service/
│   ├── LogFinder.php          # (moved from src/)
│   ├── LogParser.php          # (moved from src/, extended)
│   ├── LogScanner.php
│   ├── RemoteLogFinder.php
│   └── SSH.php
```

- `src/api.php` removed; replaced by `src/Controller/LogController.php`
- PSR-4 autoload namespace unchanged: `Mariusz\LogViewer\`

---

## Docker improvements

- `Dockerfile` – based on `mafio69/php-env:8.4-fpm-alpine` (configurable via
  `ARG BASE_IMAGE`), nginx vhost, data dir permissions
- `start.sh` – build-and-run helper script (stop old container → build →
  `docker run` on port 8123)
- `.dockerignore` added

---

## UI enhancements (`index.php`)

- Sidebar widened (200 → 280 px); file list flex increased
- Date format: `DD.MM.YYYY HH:mm:ss`
- DEBUG / INFO moved to top of level filter list
- Sort & filter controls moved to top of sidebar
- File management, directory addition, SSH section at bottom
- Duplicate directory cleanup button
- Direct file path input
- Bookmarks (★ localStorage, limit 10, validation)
- Expandable rows always enabled

---

## Bug fixes

- **Double-slash in file paths** – normalized paths no longer break rendering
  (`2f3a6bb`)
- **Log file re-rendering** – selecting a different file now correctly refreshes
  entries (`3f384fb`)

---

## Tests

New test suites covering all Service classes:

| File | Scope |
|------|-------|
| `tests/LogParserTest.php` | FPL, simple, legacy, PHP-error, nginx, APK formats |
| `tests/LogFinderTest.php` | Local file discovery |
| `tests/LogScannerTest.php` | Auto-scan logic |
| `tests/RemoteLogFinderTest.php` | Remote path building and filtering |
| `tests/SSHTest.php` | SSH connection handling |

---

## Commits

| Hash | Description |
|------|-------------|
| `3f384fb` | Fix issue with log file re-rendering |
| `1238fa9` | Add Alpine APK log format support to LogParser |
| `a5ffcb0` | Add comprehensive tests for all Service classes |
| `99ffb12` | Add nginx error log format support to LogParser |
| `2f3a6bb` | Fix double slash in file paths causing rendering issues |
| `c12d53c` | feat: Add CRT theme, DataTable, SSH, SQLite and auto-scanning |
| `948d480` | Merge PR #1 – update Composer references |
| `6191189` | Add start.sh script for Docker deployment |
| `9aba6f5` | Use ARG for base image to allow customization via build args |
| `af3b464` | Merge remote-tracking branch origin/master |
| `e250750` | Rename package to mafio69/log-viewer |
| `1f6eb01` | Update vendor paths and references |
| `904402f` | Rename package from mafio69/fast-php-log-viewer |
