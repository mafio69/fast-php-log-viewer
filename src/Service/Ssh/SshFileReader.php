<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Ssh;

use Mariusz\LogViewer\Service\SSH;

/**
 * Single responsibility: read the raw contents of one remote log file via SSH.
 *
 * Delegates actual SSH communication to {@see SSH}; this class only knows that
 * reading means "cat the file". Splitting "read" from "list" (which lives in
 * {@see SshDirectoryReader}) follows the same Host/Docker/Ssh domain split
 * already present in {@see Service\Host\LocalFileReader}.
 */
final class SshFileReader
{
    public function __construct(
        private readonly SSH $ssh,
    ) {
    }

    public function readFile(string $filePath): string
    {
        return $this->ssh->readFile($filePath);
    }
}
