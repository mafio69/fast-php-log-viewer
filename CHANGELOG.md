# Changelog

## [1.0.4] — 2026-05-04

### Added
- `LogParser`: support for PHP error log format (`[date] PHP Fatal error: ...`)
- UI: level filter replaced with always-visible colored toggle buttons (click to exclude)
- UI: sort order toggle (newest/oldest)
- UI: single-line rows with truncated message; hover tooltip shows word count and KB
- UI: entry size (B/KB) shown below location
- UI: row background color matches log level; location column colored by level
- UI: fix expand/collapse reactivity for all rows (last row fix)

## [1.0.1] — 2026-05-04

### Added
- `LogParser`: support for legacy multiline log format (`YYYY-MM-DD HH:MM:SS --- LEVEL: { ... }`)
  — extracts location from `info` field, collects multiline JSON body

## [1.0.0] — 2026-05-04

### Fixed
- `index.php`: replaced `@apply` directives with inline CSS (CDN Tailwind does not process `@apply`)
- `index.php`: added `z-10` to sticky `thead` so header stays above scrolled content

### Added
- `index.php`: `levelStyle()` and `hasContext()` helpers (consistent with `dist/`)

### Changed
- Version bumped to 1.0.0 — stable release

## [0.9.1] — 2026-05-03

### Added
- `LogParser` — parses `fast-php-logger` log lines to structured arrays
- `LogFinder` — discovers log files across all `fast-php-logger` directory structures (`Y/m`, `Y`, `Y/m/d`, flat)
- JSON API endpoint (`?action=files`, `?action=entries`)
- Vue 3 + Tailwind UI — file selector, level filter, text search, expandable JSON context, color-coded levels
- `dist/fast-php-log-viewer.php` — single-file drop-in, no Composer required
- Windows/WSL path compatibility (`\` → `/` normalization, `realpath()` fallback)
- PHPUnit test suite (13 tests, 34 assertions)
