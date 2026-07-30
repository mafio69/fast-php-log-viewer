<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Host;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\DefaultLogSources;
use Mariusz\LogViewer\Service\LogSource;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;

/**
 * Single responsibility: gather local host LogSource directories to scan.
 *
 * Combines the immutable host defaults from {@see DefaultLogSources::DEFAULTS}
 * with the user-configured entries persisted in SQLite by {@see LogConfig}.
 * It does NOT read directory contents (no file listing) — that stays the job
 * of a DirectoryReader once the controller decides to scan. Keeping collect()
 * free of filesystem scanning is what lets us call it without paying for I/O.
 *
 * Docker containers are intentionally NOT enumerated here: they live in a
 * separate table (`allowed_containers`) and are surfaced by a future
 * DockerLogSourceCollector (atom E02). Mixing them in here would re-introduce
 * an "and" in the description ("collects host sources and container sources"),
 * which is exactly the SRP violation we are untangling.
 */
final class HostLogSourceCollector implements LogSourceCollectorInterface
{
    public function __construct(
        private readonly LogConfig $logConfig,
    ) {
    }

    public function collect(): array
    {
        $sources = [];
        $byKey = [];

        foreach (DefaultLogSources::DEFAULTS as $path) {
            $key = 'local:' . $path;
            if (isset($byKey[$key])) {
                continue;
            }
            $source = new LogSource($key, $path, 'local');
            $byKey[$key] = $source;
            $sources[] = $source;
        }

        foreach ($this->logConfig->getDirectories() as $dir) {
            $type = $dir['type'] ?? 'local';

            // Docker entries belong to DockerLogSourceCollector (E02);
            // SSH entries stay here until SshLogSourceCollector (F03).
            if ($type === 'docker') {
                continue;
            }

            $key = isset($dir['name']) && $dir['name'] !== ''
                ? $dir['name']
                : $type . ':' . $dir['path'];
            if (isset($byKey[$key])) {
                continue;
            }
            $sshHost = $dir['ssh_host'] ?? null;
            $containerId = $dir['container_id'] ?? null;
            $source = new LogSource($key, $dir['path'], $type, $containerId, $sshHost);
            $byKey[$key] = $source;
            $sources[] = $source;
        }

        return $sources;
    }
}
