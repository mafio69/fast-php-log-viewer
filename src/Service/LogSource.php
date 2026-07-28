<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Immutable value object describing a single log source directory.
 *
 * A LogSource carries only *where* to find logs and *what kind* of source it
 * is; it never owns the file list of the directory (that is the job of a
 * DirectoryReader such as LogScanner::scanDirectory). Keeping the DTO free of
 * scanned content is what lets a Collector gather sources without also reading
 * them — "collects" and "scans" stay two separate responsibilities.
 */
final readonly class LogSource
{
    /**
     * @param string $key         Stable identifier, e.g. 'local:/var/log', 'container:abc:/var/log', 'ssh:host:/var/log'
     * @param string $path        Filesystem path to the directory (already resolved; no ~ or relative)
     * @param 'local'|'container'|'ssh' $type Discriminator used by readers/dispatchers
     * @param string|null $containerId Docker container id when $type === 'container'
     * @param string|null $sshHost     SSH host identifier when $type === 'ssh'
     */
    public function __construct(
        public string $key,
        public string $path,
        public string $type,
        public ?string $containerId = null,
        public ?string $sshHost = null,
    ) {
    }
}
