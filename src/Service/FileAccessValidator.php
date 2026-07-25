<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use Mariusz\LogViewer\Config\LogConfig;
use Psr\Log\LoggerInterface;

class FileAccessValidator
{
    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly LogConfig $logConfig,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @phpstan-impure Result depends on $this->logConfig->getDirectories(), which can
     * change between calls (e.g. LogController registers a new allowed directory
     * between two isFileAllowed() calls for the same request).
     */
    public function isFileAllowed(string $filePath, ?string $dirKey, bool $defaultAllowed = false): bool
    {
        $realPath = realpath($filePath);
        $checkPath = $realPath !== false ? $realPath : $filePath;
        $this->logger?->debug('isFileAllowed', ['filePath' => $filePath, 'dirKey' => $dirKey, 'realPath' => $realPath, 'checkPath' => $checkPath]);

        if ($dirKey && str_starts_with($dirKey, 'ssh:')) {
            $allowed = $realPath !== false;
            $this->logger?->debug('isFileAllowed ssh branch', ['filePath' => $filePath, 'realPath' => $realPath, 'allowed' => $allowed]);
            return $allowed;
        }

        $dirPath = $dirKey ? $this->pathResolver->resolveDirPath($dirKey) : null;
        $this->logger?->debug('isFileAllowed dir resolved', ['dirKey' => $dirKey, 'dirPath' => $dirPath]);

        if ($dirPath) {
            $resolved = realpath($dirPath);
            $checkDir = $resolved !== false ? $resolved : $dirPath;
            $this->logger?->debug('isFileAllowed dirPath resolved', ['dirPath' => $dirPath, 'resolved' => $resolved, 'checkDir' => $checkDir]);
            if ($checkPath && $checkDir && self::isPathWithinDir($checkPath, $checkDir)) {
                return true;
            }
        }

        $dirs = $this->logConfig->getDirectories();
        foreach ($dirs as $dir) {
            $resolved = realpath($dir['path']);
            $checkDir = $resolved !== false ? $resolved : $dir['path'];
            if ($checkPath && $checkDir && self::isPathWithinDir($checkPath, $checkDir)) {
                $this->logger?->debug('isFileAllowed fallback match', ['dir' => $dir['name'], 'checkDir' => $checkDir]);
                return true;
            }
        }

        $this->logger?->debug('isFileAllowed denied', ['filePath' => $filePath, 'realPath' => $realPath]);
        return $defaultAllowed;
    }

    public function isFileInDirectory(string $filePath, string $dirPath): bool
    {
        $realFile = realpath($filePath);
        $realDir = realpath($dirPath);
        return $realFile !== false && $realDir !== false && self::isPathWithinDir($realFile, $realDir);
    }

    /**
     * Directory-boundary-safe containment check. A plain str_starts_with($path, $dir)
     * would let an allowed dir like "/logs" also match a sibling "/logs-other", so this
     * requires an exact match or a match at a path-separator boundary.
     */
    private static function isPathWithinDir(string $path, string $dir): bool
    {
        $dir = rtrim($dir, '/');
        return $path === $dir || str_starts_with($path, $dir . '/');
    }
}
