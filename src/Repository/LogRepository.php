<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

use Mariusz\LogViewer\Repository\Model\LogEntry;
use Mariusz\LogViewer\Repository\Model\LogFile;
use Mariusz\LogViewer\Service\LogParser;

/**
 * Repository for log files and entries.
 */
class LogRepository
{
    private LogParser $parser;

    public function __construct(LogParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get log files from a directory.
     *
     * @param string $directory
     * @return LogFile[]
     */
    public function getLogFiles(string $directory): array
    {
        $files = [];
        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'log') {
                $files[] = new LogFile(
                    $file->getPathname(),
                    $file->getFilename(),
                    $directory,
                    $file->getSize(),
                    date('Y-m-d H:i:s', $file->getMTime())
                );
            }
        }

        return $files;
    }

    /**
     * Get log entries from a file.
     *
     * @param string $filePath
     * @param array $filters
     * @return LogEntry[]
     */
    public function getLogEntries(string $filePath, array $filters = []): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        $entries = $this->parser->parseString($content);

        if (!empty($filters)) {
            $entries = $this->filterEntries($entries, $filters);
        }

        return $entries;
    }

    /**
     * Filter log entries.
     *
     * @param array $entries
     * @param array $filters
     * @return array
     */
    private function filterEntries(array $entries, array $filters): array
    {
        return array_filter($entries, function ($entry) use ($filters) {
            foreach ($filters as $key => $value) {
                if (isset($entry[$key]) && $entry[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }
}