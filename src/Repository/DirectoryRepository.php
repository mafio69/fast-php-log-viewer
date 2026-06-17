<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

use Mariusz\LogViewer\Config\LogConfig;

/**
 * Repository for log directories.
 */
class DirectoryRepository
{
    private LogConfig $config;

    public function __construct(LogConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Get all log directories.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->config->getLogDirectories();
    }

    /**
     * Get log directory by key.
     *
     * @param string $key
     * @return array|null
     */
    public function getByKey(string $key): ?array
    {
        $directories = $this->config->getLogDirectories();

        return $directories[$key] ?? null;
    }

    /**
     * Save log directory.
     *
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function save(string $key, array $data): bool
    {
        return $this->config->addLogDirectory($key, $data);
    }

    /**
     * Delete log directory.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->config->deleteLogDirectory($key);
    }
}
