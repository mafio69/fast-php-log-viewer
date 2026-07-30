<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Docker;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogSource;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;

/**
 * Single responsibility: gather Docker container LogSource directories to scan.
 *
 * Reads docker-type entries from {@see LogConfig::getDirectories()} — it does
 * NOT enumerate local host paths or SSH hosts. Keeping one collector per
 * source type ("host", "docker", "ssh") prevents the "and" in "collects host
 * and docker and ssh sources" that the old DefaultLogSourceCollector had.
 */
final class DockerLogSourceCollector implements LogSourceCollectorInterface
{
    public function __construct(
        private readonly LogConfig $logConfig,
    ) {
    }

    public function collect(): array
    {
        $sources = [];

        foreach ($this->logConfig->getDirectories() as $dir) {
            if (($dir['type'] ?? 'local') !== 'docker') {
                continue;
            }

            $containerId = $dir['container_id'] ?? null;
            if ($containerId === null || $containerId === '') {
                continue;
            }

            $key = isset($dir['name']) && $dir['name'] !== ''
                ? $dir['name']
                : 'container:' . $containerId . ':' . $dir['path'];

            $sources[] = new LogSource(
                $key,
                $dir['path'],
                'container',
                $containerId,
            );
        }

        return $sources;
    }
}
