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
            if ($checkPath && $checkDir && str_starts_with($checkPath, $checkDir)) {
                return true;
            }
        }

        $dirs = $this->logConfig->getDirectories();
        foreach ($dirs as $dir) {
            $resolved = realpath($dir['path']);
            $checkDir = $resolved !== false ? $resolved : $dir['path'];
            if ($checkPath && $checkDir && str_starts_with($checkPath, $checkDir)) {
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
        return $realFile !== false && $realDir !== false && str_starts_with($realFile, $realDir);
    }
}
