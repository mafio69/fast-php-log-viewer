<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use RuntimeException;

class DockerExecService
{
    private const SOCKET = '/var/run/docker.sock';
    private const TIMEOUT = 10;
    private const API_VERSION = 'v1.47';

    public function isAvailable(): bool
    {
        return file_exists(self::SOCKET);
    }

    public function readFile(string $containerId, string $filePath): string
    {
        $this->validateContainerId($containerId);
        $this->validateFilePath($filePath);

        $execId = $this->createExec($containerId, $filePath);
        $output = $this->startExec($execId);

        if ($output === '') {
            throw new RuntimeException("file_not_found");
        }

        return $output;
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

    private function createExec(string $containerId, string $filePath): string
    {
        $payload = json_encode([
            'AttachStdout' => true,
            'AttachStderr' => true,
            'Cmd' => ['cat', $filePath],
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
