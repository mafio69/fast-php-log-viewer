<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use RuntimeException;

class DockerExecService
{
    private const SOCKET = '/var/run/docker.sock';
    private const TIMEOUT = 10;
    private const API_VERSION = 'v1.47';
    private const DEFAULT_ALLOWED_PATH_PREFIXES = ['/var/log/', '/var/www/html/logs/'];

    /** @var string[] */
    private array $allowedContainers;
    /** @var string[] */
    private array $allowedPathPrefixes;

    /**
     * @param string[]|null $allowedContainers Container names/IDs this instance may read from.
     *   Defaults to the LOG_VIEWER_ALLOWED_CONTAINERS env var (comma-separated). Empty means
     *   no container is allowed - this feature is opt-in, not opt-out, since it exposes
     *   arbitrary files from any container reachable via the mounted Docker socket.
     * @param string[]|null $allowedPathPrefixes File path prefixes readable inside an allowed
     *   container. Defaults to LOG_VIEWER_ALLOWED_CONTAINER_PATHS env var, falling back to
     *   common log locations.
     */
    public function __construct(?array $allowedContainers = null, ?array $allowedPathPrefixes = null)
    {
        $this->allowedContainers = $allowedContainers ?? self::containersFromEnv();

        $envPathPrefixes = self::envList('LOG_VIEWER_ALLOWED_CONTAINER_PATHS');
        $this->allowedPathPrefixes = $allowedPathPrefixes ?? ($envPathPrefixes ?: self::DEFAULT_ALLOWED_PATH_PREFIXES);
    }

    /**
     * Exposed so callers (e.g. DI wiring) can merge the env-configured allow-list
     * with a runtime-editable one (e.g. from LogConfig::getAllowedContainers())
     * without duplicating the env-parsing rules.
     *
     * @return string[]
     */
    public static function containersFromEnv(): array
    {
        return self::envList('LOG_VIEWER_ALLOWED_CONTAINERS');
    }

    /**
     * The path-prefix baseline before any runtime (DB-backed) additions: env
     * override if set, otherwise the built-in common log locations. Exposed for
     * the same reason as containersFromEnv() - DI wiring merges this with
     * LogConfig::getAllowedContainerPaths() without duplicating the env/default
     * fallback rule.
     *
     * @return string[]
     */
    public static function defaultPathPrefixes(): array
    {
        $envPathPrefixes = self::envList('LOG_VIEWER_ALLOWED_CONTAINER_PATHS');
        return $envPathPrefixes ?: self::DEFAULT_ALLOWED_PATH_PREFIXES;
    }

    /**
     * @return string[]
     */
    private static function envList(string $name): array
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function isAvailable(): bool
    {
        return file_exists(self::SOCKET);
    }

    public function readFile(string $containerId, string $filePath): string
    {
        $this->validateContainerId($containerId);
        $this->validateFilePath($filePath);
        $filePath = $this->normalizePath($filePath);
        $this->assertContainerAllowed($containerId);
        $this->assertPathAllowed($filePath);

        $execId = $this->createExec($containerId, ['cat', $filePath]);
        $output = $this->startExec($execId);

        if ($output === '') {
            throw new RuntimeException('file_not_found');
        }

        return $output;
    }

    /**
     * Lists regular files directly inside $dirPath (non-recursive) in an allowed container.
     * Uses `find -exec stat` as a single argv command (no shell involved - $dirPath is passed
     * as a distinct Cmd array element, never interpolated into a shell string), so it carries
     * the same traversal/allow-list protection as readFile() without any injection risk.
     *
     * @return array<int, array{file: string, date: string, size: int}>
     */
    public function listFiles(string $containerId, string $dirPath): array
    {
        $this->validateContainerId($containerId);
        $this->validateFilePath($dirPath);
        $dirPath = $this->normalizePath($dirPath);
        $this->assertContainerAllowed($containerId);
        $this->assertPathAllowed($dirPath);

        // A literal tab byte, not the two-char "\t", is embedded directly in the
        // format string: BusyBox stat (Alpine images) doesn't interpret "\t" as an
        // escape sequence the way GNU stat does - it would print a literal
        // backslash+t instead of a tab, breaking the parseListing() regex below.
        $execId = $this->createExec($containerId, [
            'find', $dirPath, '-maxdepth', '1', '-type', 'f',
            '-exec', 'stat', '-c', "%Y\t%s\t%n", '{}', ';',
        ]);
        $output = $this->startExec($execId);

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

    private function validateContainerId(string $containerId): void
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/', $containerId)) {
            throw new RuntimeException('invalid_container_id');
        }
    }

    private function validateFilePath(string $filePath): void
    {
        if ($filePath === '' || !str_starts_with($filePath, '/')) {
            throw new RuntimeException('invalid_file_path');
        }
        if (str_contains($filePath, "\0") || str_contains($filePath, "\n") || str_contains($filePath, "\r")) {
            throw new RuntimeException('invalid_file_path');
        }
    }

    private function assertContainerAllowed(string $containerId): void
    {
        if (!in_array($containerId, $this->allowedContainers, true)) {
            throw new RuntimeException('container_not_allowed');
        }
    }

    /**
     * Collapses "." and ".." segments before the allow-list check, so a path
     * like "/var/log/../../etc/passwd" - which passes a naive str_starts_with()
     * prefix check against "/var/log/" - is normalized to "/etc/passwd" first
     * and correctly rejected. The normalized path is also what actually gets
     * executed (via createExec()), so what's checked matches what runs.
     */
    private function normalizePath(string $filePath): string
    {
        $normalized = [];
        foreach (explode('/', $filePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }
            $normalized[] = $segment;
        }

        return '/' . implode('/', $normalized);
    }

    private function assertPathAllowed(string $filePath): void
    {
        foreach ($this->allowedPathPrefixes as $prefix) {
            // A bare directory like "/var/log" (no trailing slash) is a legitimate
            // input for listFiles() but never satisfies str_starts_with() against
            // a "/var/log/" prefix - accept an exact match of the prefix itself too.
            if ($filePath === rtrim($prefix, '/') || str_starts_with($filePath, $prefix)) {
                return;
            }
        }

        throw new RuntimeException('path_not_allowed');
    }

    /**
     * @param string[] $cmd Argv array executed directly in the container - no shell involved,
     *   so callers don't need to shell-escape individual arguments.
     */
    private function createExec(string $containerId, array $cmd): string
    {
        $payload = json_encode([
            'AttachStdout' => true,
            'AttachStderr' => true,
            'Cmd' => $cmd,
        ]);

        $path = '/' . self::API_VERSION . '/containers/' . $containerId . '/exec';
        $response = $this->dockerPost($path, $payload);

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['Id'])) {
            $message = is_array($data) ? ($data['message'] ?? 'unknown error') : 'invalid response';
            if (str_contains($message, 'No such container')) {
                throw new RuntimeException('container_not_found');
            }
            throw new RuntimeException('docker_exec_failed: ' . $message);
        }

        return $data['Id'];
    }

    private function startExec(string $execId): string
    {
        $payload = json_encode([
            'Detach' => false,
            'Tty' => false,
        ]);

        $path = '/' . self::API_VERSION . '/exec/' . $execId . '/start';
        [$headers, $body] = $this->dockerPostRaw($path, $payload);

        return $this->demuxStream($body);
    }

    private function demuxStream(string $raw): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length) {
            if ($offset + 8 > $length) {
                break;
            }

            $header = substr($raw, $offset, 8);
            $streamType = ord($header[0]);
            $size = unpack('N', substr($header, 4, 4))[1];

            $offset += 8;

            if ($offset + $size > $length) {
                break;
            }

            $chunk = substr($raw, $offset, $size);
            $offset += $size;

            if ($streamType === 1 || $streamType === 2) {
                $output .= $chunk;
            }
        }

        return $output;
    }

    private function dockerPost(string $path, string $body): string
    {
        [, $responseBody] = $this->dockerPostRaw($path, $body);
        return $responseBody;
    }

    /**
     * @return array{0: string, 1: string} [headers, body]
     */
    private function dockerPostRaw(string $path, string $body): array
    {
        $errno = 0;
        $errstr = '';

        $fp = @stream_socket_client(
            'unix://' . self::SOCKET,
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
        );

        if (!$fp) {
            throw new RuntimeException(
                "docker_socket_failed ({$errno}: {$errstr})"
            );
        }

        stream_set_timeout($fp, self::TIMEOUT);

        $contentLength = strlen($body);
        $request = "POST {$path} HTTP/1.0\r\n";
        $request .= "Host: localhost\r\n";
        $request .= "Content-Type: application/json\r\n";
        $request .= "Content-Length: {$contentLength}\r\n";
        $request .= "Connection: close\r\n\r\n";
        $request .= $body;

        fwrite($fp, $request);

        $response = '';
        while (!feof($fp)) {
            $chunk = @fread($fp, 32768);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
        }
        fclose($fp);

        if ($response === '') {
            throw new RuntimeException('docker_no_response');
        }

        $parts = explode("\r\n\r\n", $response, 2);

        if (count($parts) < 2) {
            throw new RuntimeException('docker_invalid_response');
        }

        $headers = $parts[0];
        $body = $parts[1] ?? '';

        $statusLine = strtok($headers, "\r\n");
        if ($statusLine && preg_match('#HTTP/\d\.\d\s+(\d{3})#', $statusLine, $m)) {
            $statusCode = (int) $m[1];
            if ($statusCode >= 500) {
                throw new RuntimeException('docker_server_error');
            }
            if ($statusCode === 404) {
                if (str_contains($body, 'No such container')) {
                    throw new RuntimeException('container_not_found');
                }
                throw new RuntimeException('docker_not_found');
            }
            if ($statusCode >= 400) {
                throw new RuntimeException('docker_request_failed: ' . $statusCode);
            }
        }

        return [$headers, $body];
    }
}
