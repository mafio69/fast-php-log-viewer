<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use Mariusz\LogViewer\Config\LogConfig;
use Psr\Log\LoggerInterface;

class PathResolver
{
    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function resolvePath(string $path): string
    {
        $this->logger?->debug('resolvePath input', ['path' => $path]);

        if (str_starts_with($path, '~/')) {
            $result = ($_SERVER['HOME'] ?? '/root') . '/' . substr($path, 2);
            $this->logger?->debug('resolvePath tilde branch', ['home' => ($_SERVER['HOME'] ?? '/root'), 'result' => $result]);
            return $result;
        }
        if (!str_starts_with($path, '/')) {
            $result = dirname(__DIR__, 2) . '/' . $path;
            $this->logger?->debug('resolvePath relative branch', ['root' => dirname(__DIR__, 2), 'result' => $result]);
            return $result;
        }
        $this->logger?->debug('resolvePath absolute branch', ['result' => $path]);
        return $path;
    }

    public function resolveDirPath(string $key): ?string
    {
        $this->logger?->debug('resolveDirPath input', ['key' => $key]);

        if (str_starts_with($key, 'ssh:')) {
            $this->logger?->debug('resolveDirPath ssh branch', ['key' => $key]);
            return null;
        }

        if (str_starts_with($key, '/')) {
            $result = $this->resolvePath($key);
            $this->logger?->debug('resolveDirPath absolute branch', ['key' => $key, 'result' => $result]);
            return $result;
        }

        if (str_contains($key, ':')) {
            $path = substr($key, strpos($key, ':') + 1);
            $result = $this->resolvePath($path);
            $this->logger?->debug('resolveDirPath colon branch', ['key' => $key, 'pathAfterColon' => $path, 'result' => $result]);
            return $result;
        }

        $dirs = $this->logConfig->getDirectories();
        foreach ($dirs as $dir) {
            if ($dir['name'] === $key) {
                $this->logger?->debug('resolveDirPath db branch', ['key' => $key, 'path' => $dir['path']]);
                return $dir['path'];
            }
        }

        $this->logger?->debug('resolveDirPath fallback branch', ['key' => $key]);
        return $this->resolvePath($key);
    }
}
