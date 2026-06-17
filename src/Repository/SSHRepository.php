<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Repository\Model\SSHConnection;

/**
 * Repository for SSH connections.
 */
class SSHRepository
{
    private LogConfig $config;

    public function __construct(LogConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Get all SSH connections.
     *
     * @return SSHConnection[]
     */
    public function getAll(): array
    {
        $connections = $this->config->getSSHConnections();

        return array_map(fn ($conn) => SSHConnection::fromArray($conn), $connections);
    }

    /**
     * Get SSH connection by ID.
     *
     * @param int $id
     * @return SSHConnection|null
     */
    public function getById(int $id): ?SSHConnection
    {
        $connection = $this->config->getSSHConnection($id);

        return $connection ? SSHConnection::fromArray($connection) : null;
    }

    /**
     * Save SSH connection.
     *
     * @param SSHConnection $connection
     * @return bool
     */
    public function save(SSHConnection $connection): bool
    {
        return $this->config->saveSSHConnection($connection->toArray());
    }

    /**
     * Delete SSH connection.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->config->deleteSSHConnection($id);
    }
}
