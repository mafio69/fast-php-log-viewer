<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Ssh;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogSource;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;

/**
 * Single responsibility: gather SSH LogSource directories to scan.
 *
 * Reads ssh-type entries from {@see LogConfig::getDirectories()} — it does
 * NOT enumerate local host paths or Docker containers. Keeping one collector
 * per source type ("host", "docker", "ssh") prevents the "and" in "collects
 * host and docker and ssh sources".
 */
final class SshLogSourceCollector implements LogSourceCollectorInterface
{
    public function __construct(
        private readonly LogConfig $logConfig,
    ) {
    }

    public function collect(): array
    {
        $sources = [];

        foreach ($this->logConfig->getDirectories() as $dir) {
            if (($dir['type'] ?? 'local') !== 'ssh') {
                continue;
            }

            $sshHost = $dir['ssh_host'] ?? null;
            if ($sshHost === null || $sshHost === '') {
                continue;
            }

            $key = isset($dir['name']) && $dir['name'] !== ''
                ? $dir['name']
                : 'ssh:' . $sshHost . ':' . $dir['path'];

            $sources[] = new LogSource(
                $key,
                $dir['path'],
                'ssh',
                null,
                $sshHost,
            );
        }

        return $sources;
    }
}
