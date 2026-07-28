<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Single source of truth for the host log directories the viewer will scan by
 * default. Hardcoding log paths elsewhere (LogScanner, controllers, config) is
 * forbidden — read them from here instead.
 *
 * The three default source paths are chosen to keep the attack surface small
 * and remain portable across host, Dev Container, and production deployments:
 *
 *   - `/var/log`  — POSIX-standard system log directory.
 *   - `~/logs`   — per-process HOME-relative logs (resolves via PathResolver).
 *   - `./logs`   — project-local logs (resolves relative to ROOT_DIR).
 *
 * Presets (nginx/apache2/php-fpm sub-directories) are NOT defaults: they are
 * opt-in suggestions surfaced by the SetupWizard. Keep them on `presets()`
 * so H01 can consume them without re-introducing hardcoded defaults.
 */
final class DefaultLogSources
{
    public const DEFAULTS = [
        '/var/log',
        '~/logs',
        './logs',
    ];

    /**
     * Common log sub-directories that a user may want to register explicitly
     * (opt-in, never autoloaded). Surfaced by the SetupWizard as click-to-add
     * suggestions so users do not have to type the paths manually.
     *
     * @return array<int, string>
     */
    public static function presets(): array
    {
        return [
            '/var/log/nginx',
            '/var/log/apache2',
            '/var/log/php-fpm',
        ];
    }
}
