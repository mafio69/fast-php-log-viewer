<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogFinder;
use Mariusz\LogViewer\Service\LogParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogControllerSlim
{
    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ConfigManager $configManager
    ) {
    }

    public function getDirectories(Request $request, Response $response): Response
    {
        $dirs = $this->logConfig->getDirectories();

        if (!$this->configManager->isSshEnabled()) {
            $dirs = array_filter($dirs, fn($d) => ($d['type'] ?? 'local') !== 'ssh');
        }

        $result = array_map(function ($dir) {
            return [
                'key' => $dir['name'],
                'path' => $dir['path'],
                'type' => $dir['type'] ?? 'local'
            ];
        }, $dirs);

        $response->getBody()->write(json_encode(array_values($result)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getFiles(Request $request, Response $response): Response
    {
        $dirKey = $request->getQueryParams()['dir'] ?? null;
        if (!$dirKey) {
            $response->getBody()->write(json_encode(['error' => 'missing_dir']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $dirs = $this->logConfig->getDirectories();
        $dir = null;
        foreach ($dirs as $d) {
            if ($d['name'] === $dirKey) {
                $dir = $d;
                break;
            }
        }

        if (!$dir) {
            $response->getBody()->write(json_encode(['error' => 'directory_not_found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $finder = new LogFinder();
        $files = $finder->findAll($dir['path']);

        $result = array_map(function ($file) {
            return [
                'file' => $file['file'],
                'date' => $file['date'],
                'size' => $file['size']
            ];
        }, $files);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getEntries(Request $request, Response $response): Response
    {
        $filePath = $request->getQueryParams()['file'] ?? null;
        if (!$filePath) {
            $response->getBody()->write(json_encode(['error' => 'missing_file']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Walidacja ścieżki - sprawdź czy plik jest w dozwolonych katalogach
        $dirs = $this->logConfig->getDirectories();
        $allowed = false;
        $realPath = realpath($filePath);

        foreach ($dirs as $dir) {
            $dirPath = realpath($dir['path']);
            if ($realPath && $dirPath && str_starts_with($realPath, $dirPath)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $response->getBody()->write(json_encode(['error' => 'access_denied']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!file_exists($filePath)) {
            $response->getBody()->write(json_encode(['error' => 'file_not_found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $level = $request->getQueryParams()['level'] ?? null;
        $parser = new LogParser();
        $entries = $parser->parseFile($filePath);

        if ($level) {
            $entries = array_filter($entries, fn($e) => strtoupper($e['level']) === strtoupper($level));
        }

        $response->getBody()->write(json_encode($entries));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
