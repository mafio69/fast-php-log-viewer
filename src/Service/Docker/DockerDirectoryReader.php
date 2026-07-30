<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service\Docker;

use Mariusz\LogViewer\Service\DockerExecService;

/**
 * Single responsibility: list regular files inside a directory on an allowed
 * Docker container. It does NOT read file contents — that stays the job of
 * DockerExecService::readFile(). Splitting "list" from "read" keeps the same
 * Host/Docker/Ssh domain split already present in Service/Host/.
 *
 * Docker communication (socket, exec, stream demux) is delegated to
 * DockerExecService; this class only knows *what* command to run (find+stat)
 * and *how* to parse its output. Path validation is also delegated so the
 * allow-list rules stay in one place without duplication.
 */
final class DockerDirectoryReader
{
    public function __construct(
        private readonly DockerExecService $docker,
    ) {
    }

    /**
     * Lists regular files directly inside $dirPath (non-recursive) in an
     * allowed container. Uses `find -exec stat` as a single argv command (no
     * shell involved — $dirPath is a distinct argv element, never interpolated
     * into a shell string), so it carries the same traversal/allow-list
     * protection as DockerExecService::readFile() without any injection risk.
     *
     * @return array<int, array{file: string, date: string, size: int}>
     */
    public function listFiles(string $containerId, string $dirPath): array
    {
        $dirPath = $this->docker->validateAndNormalizePath($dirPath);

        // A literal tab byte, not the two-char "\t", is embedded directly in
        // the format string: BusyBox stat (Alpine images) doesn't interpret
        // "\t" as an escape sequence the way GNU stat does — it would print a
        // literal backslash+t instead of a tab, breaking the regex below.
        $output = $this->docker->exec($containerId, [
            'find', $dirPath, '-maxdepth', '1', '-type', 'f',
            '-exec', 'stat', '-c', "%Y\t%s\t%n", '{}', ';',
        ]);

        return $this->parseListing($output);
    }

    /**
     * @return array<int, array{file: string, date: string, size: int}>
     */
    private function parseListing(string $output): array
    {
        $files = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (!preg_match('/^(\d+)\t(\d+)\t(.+)$/', $line, $m)) {
                continue;
            }
            [, $mtime, $size, $path] = $m;
            $files[] = [
                'file' => basename($path),
                'date' => date('Y-m-d H:i:s', (int)$mtime),
                'size' => (int)$size,
            ];
        }

        return $files;
    }
}
