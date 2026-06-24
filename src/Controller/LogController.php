<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogController
{
    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ConfigManager $configManager,
        private readonly LogFinderInterface $logFinder
    ) {
    }

    public function getDirectories(Request $request, Response $response): Response
    {
        $this->logConfig->cleanupAuto();
        $dirs = $this->logConfig->getValidDirectories();

        if (!$this->configManager->isSshEnabled()) {
            $dirs = array_filter($dirs, fn($d) => ($d['type'] ?? 'local') !== 'ssh');
        }

        $response->getBody()->write(json_encode(array_values($dirs)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getFiles(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $path = $params['path'] ?? null;
        $dirKey = $params['dir'] ?? null;

        // Default directories bypass DB lookup — scan path directly
        if ($path) {
            $absPath = $this->resolvePath($path);
            $files = $this->logFinder->findAll($absPath);
            $basePath = rtrim($absPath, '/');
            $result = array_map(fn($f) => [
                'file' => $basePath . '/' . $f['file'],
                'date' => $f['date'],
                'size' => $f['size'],
            ], $files);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        }

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

        $files = $this->logFinder->findAll($dir['path']);

        $basePath = rtrim($dir['path'], '/');
        $result = array_map(fn($f) => [
            'file' => $basePath . '/' . $f['file'],
            'date' => $f['date'],
            'size' => $f['size'],
        ], $files);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Resolve a directory key (docker:/var/log, host:/var/log, etc.) to an absolute path.
     * Returns null if the key is unknown and not found in DB.
     */
    private function resolveDirPath(string $key): ?string
    {
        // SSH directories — not validated against local paths
        if (str_starts_with($key, 'ssh:')) {
            return null;
        }

        // Default directories with colon prefix (docker:/var/log, host:/var/log, etc.)
        if (str_contains($key, ':')) {
            $path = substr($key, strpos($key, ':') + 1);
            return $this->resolvePath($path);
        }

        // DB-saved directory — look up by name
        $dirs = $this->logConfig->getDirectories();
        foreach ($dirs as $dir) {
            if ($dir['name'] === $key) {
                return $dir['path'];
            }
        }

        return null;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return ($_SERVER['HOME'] ?? '/root') . '/' . substr($path, 2);
        }
        if (!str_starts_with($path, '/')) {
            return dirname(__DIR__, 2) . '/' . $path;
        }
        return $path;
    }

    public function getEntries(Request $request, Response $response): Response
    {
        $filePath = $request->getQueryParams()['file'] ?? null;
        if (!$filePath) {
            $response->getBody()->write(json_encode(['error' => 'missing_file']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Walidacja ścieżki
        $realPath = realpath($filePath);
        $allowed = false;

        $dirKey = $request->getQueryParams()['dir'] ?? null;

        // SSH — pliki już zweryfikowane przy pobieraniu
        if ($dirKey && str_starts_with($dirKey, 'ssh:')) {
            $allowed = $realPath !== false;
        } else {
            $dirPath = $dirKey ? $this->resolveDirPath($dirKey) : null;

            if ($dirPath) {
                $resolved = realpath($dirPath);
                if ($realPath && $resolved && str_starts_with($realPath, $resolved)) {
                    $allowed = true;
                }
            } else {
                // Fallback: sprawdź czy plik jest w zapisanych katalogach
                $dirs = $this->logConfig->getDirectories();
                foreach ($dirs as $dir) {
                    $resolved = realpath($dir['path']);
                    if ($realPath && $resolved && str_starts_with($realPath, $resolved)) {
                        $allowed = true;
                        break;
                    }
                }
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