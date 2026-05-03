# Changelog

## [0.9.1] — 2026-05-03

### Added
- `LogParser` — parses `fast-php-logger` log lines to structured arrays
- `LogFinder` — discovers log files across all `fast-php-logger` directory structures (`Y/m`, `Y`, `Y/m/d`, flat)
- JSON API endpoint (`?action=files`, `?action=entries`)
- Vue 3 + Tailwind UI — file selector, level filter, text search, expandable JSON context, color-coded levels
- `dist/fast-php-log-viewer.php` — single-file drop-in, no Composer required
- Windows/WSL path compatibility (`\` → `/` normalization, `realpath()` fallback)
- PHPUnit test suite (13 tests, 34 assertions)
