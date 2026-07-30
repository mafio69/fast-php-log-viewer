<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Ssh;

use Mariusz\LogViewer\Service\SSH;

/**
 * Single responsibility: list regular files in a remote directory via SSH.
 *
 * Replaces the older RetemoteLogFinder. Delegates SSH communication to
 * {@see SSH}; this class only knows what "find" command to run and how to
 * interpret its output. Keeping "list" separate from "read" (which lives in
 * {@see SshFileReader}) prevents the "and" in "finds AND reads remote log
 * files" that the old {@see RemoteLogFinder} had.
 */
final class SshDirectoryReader
{
    public function __construct(
        private readonly SSH $ssh,
    ) {
    }

    /**
     * @return array<int, array{path: string, name: string, size: int}>
     */
    public function findAll(string $remotePath, bool $allFiles = false): array
    {
        if (!$this->ssh->directoryExists($remotePath)) {
            return [];
        }

        $files = [];

        if ($allFiles) {
            $command = sprintf('find %s -maxdepth 1 -type f 2>/dev/null', escapeshellarg($remotePath));
            $output = $this->ssh->exec($command);

            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (!empty($line) && $this->ssh->fileExists($line)) {
                    $files[] = [
                        'path' => $line,
                        'name' => basename($line),
                        'size' => $this->ssh->fileSize($line),
                    ];
                }
            }
        } else {
            $patterns = [
                '*.log',
                '*error*',
                '*debug*',
                '*access*',
                '*.php',
                '*.txt',
                'messages',
                'syslog',
                'btmp',
                'wtmp',
                'lastlog',
                '*.out',
                '*.err',
            ];

            foreach ($patterns as $pattern) {
                $command = sprintf('find %s -maxdepth 3 -name "%s" -type f 2>/dev/null', escapeshellarg($remotePath), $pattern);
                $output = $this->ssh->exec($command);

                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (!empty($line) && $this->ssh->fileExists($line)) {
                        $files[] = [
                            'path' => $line,
                            'name' => basename($line),
                            'size' => $this->ssh->fileSize($line),
                        ];
                    }
                }
            }
        }

        $files = array_values(array_unique($files, SORT_REGULAR));
        usort($files, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $files;
    }
}
