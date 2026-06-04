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

**Step 1.**

```sh
composer require mafio69/log-viewer
```

**Step 2.** Create a single entry point file (e.g. `log-viewer.php` in your webroot):

```php
<?php
define('LOG_DIR', __DIR__ . '/logs');   // ← adjust path to your logs
require_once __DIR__ . '/vendor/autoload.php';

if (isset($_GET['action'])) {
    require_once __DIR__ . '/vendor/mafio69/log-viewer/src/api.php';
    exit;
}

require_once __DIR__ . '/vendor/mafio69/log-viewer/index.php';
```

**Step 3.** Open `http://yourproject.local/log-viewer.php` — done.

A ready-to-use example file is included at [`example/viewer.php`](example/viewer.php).

---

## Usage in Docker (fast-php-logger suite)

If you use [docker-fast-logger](https://github.com/mafio69/docker-fast-logger), the viewer is pre-configured.
Just open `http://localhost:8080/logs` — no setup needed.

---

## Features

- Lists log files sorted by date (newest first)
- Parses `fast-php-logger` format: `[datetime] [LEVEL] [file:line] message {context}`
- Filter by log level (DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY)
- Full-text search across message and location
- Expandable JSON context per entry
- Color-coded levels
- Vue 3 + Tailwind — no build step, no node_modules

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

## Structure

```
fast-php-log-viewer/
├── src/
│   ├── LogParser.php   ← parses log lines to arrays
│   ├── LogFinder.php   ← finds log files by directory structure
│   └── api.php         ← JSON endpoint (?action=files / ?action=entries)
├── dist/
│   └── fast-php-log-viewer.php  ← single-file drop-in (no Composer)
├── example/
│   └── viewer.php      ← ready-to-use Composer entry point
├── tests/
│   ├── LogParserTest.php
│   └── LogFinderTest.php
├── index.php           ← Vue 3 + Tailwind UI entry point
└── composer.json
```

## fast-php-* suite

| Package | Description |
|---|---|
| [fast-php-logger](https://github.com/mafio69/fast-php-logger) | PSR-3 file logger |
| [fast-php-log-viewer](https://github.com/mafio69/fast-php-log-viewer) | Log viewer (this package) |
| [docker-fast-logger](https://github.com/mafio69/docker-fast-logger) | Docker dev environment with both pre-installed |

## License

MIT
