<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

interface LogFinderInterface
{
    /**
     * Finds all log files in a given directory.
     *
     * @param string $path The directory path to search in.
     * @return array<int, array{file: string, date: string, size: int}>
     */
    public function findAll(string $path): array;
}