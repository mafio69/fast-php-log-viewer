<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Routing;

final class LegacyRouter
{
    private const ACTION_MAP = [
        'directories'        => '/api/directories',
        'files'              => '/api/files',
        'entries'            => '/api/entries',
        'config-add-dir'     => '/api/config/directories',
        'ssh-test-connection'=> '/api/ssh/test-connection',
        'ssh-list-files'     => '/api/ssh/list-files',
        'ssh-read-file'      => '/api/ssh/read-file',
        'ssh-download-file'  => '/api/ssh/download-file',
        'setup-status'       => '/api/setup/status',
        'setup-step'         => '/api/setup/step',
        'setup-migrate-ssh'  => '/api/setup/migrate-ssh',
        'app-config'         => '/api/app-config',
    ];

    public static function getActionMap(): array
    {
        return self::ACTION_MAP;
    }

    public static function hasAction(string $action): bool
    {
        return isset(self::ACTION_MAP[$action]);
    }

    public static function resolve(string $action): ?string
    {
        return self::ACTION_MAP[$action] ?? null;
    }

    public static function rewriteRequestUri(string $action, array $queryParams): string
    {
        $path = self::ACTION_MAP[$action] ?? '/';
        unset($queryParams['action']);
        $query = http_build_query($queryParams);
        return $path . ($query ? '?' . $query : '');
    }
}
