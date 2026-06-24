<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class LogController
{
    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ConfigManager $configManager,
        private readonly LogFinderInterface $logFinder,
        private readonly ?LoggerInterface $logger = null
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

        $this->logger?->debug('getFiles called', ['path' => $path, 'dirKey' => $dirKey]);

        // Default directories bypass DB lookup — scan path directly
        if ($path) {
            $absPath = $this->resolvePath($path);
            $this->logger?->debug('getFiles path resolved', ['path' => $path, 'absPath' => $absPath]);
            $files = $this->logFinder->findAll($absPath);
            $this->logger?->debug('getFiles files found', ['absPath' => $absPath, 'count' => count($files)]);
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
        $this->logger?->debug('resolveDirPath input', ['key' => $key]);

        // SSH directories — not validated against local paths
        if (str_starts_with($key, 'ssh:')) {
            $this->logger?->debug('resolveDirPath ssh branch', ['key' => $key]);
            return null;
        }

        // Direct filesystem path — use as-is
        if (str_starts_with($key, '/')) {
            $result = $this->resolvePath($key);
            $this->logger?->debug('resolveDirPath absolute branch', ['key' => $key, 'result' => $result]);
            return $result;
        }

        // Default directories with colon prefix (docker:/var/log, host:/var/log, etc.)
        if (str_contains($key, ':')) {
            $path = substr($key, strpos($key, ':') + 1);
            $result = $this->resolvePath($path);
            $this->logger?->debug('resolveDirPath colon branch', ['key' => $key, 'pathAfterColon' => $path, 'result' => $result]);
            return $result;
        }

        // DB-saved directory — look up by name
        $dirs = $this->logConfig->getDirectories();
        foreach ($dirs as $dir) {
            if ($dir['name'] === $key) {
                $this->logger?->debug('resolveDirPath db branch', ['key' => $key, 'path' => $dir['path']]);
                return $dir['path'];
            }
        }

        // Fallback: treat key as direct path (for defaultDirectories from frontend)
        $this->logger?->debug('resolveDirPath fallback branch', ['key' => $key]);
        return $this->resolvePath($key);
    }

    private function resolvePath(string $path): string
    {
        $this->logger?->debug('resolvePath input', ['path' => $path]);

        if (str_starts_with($path, '~/')) {
            $result = ($_SERVER['HOME'] ?? '/root') . '/' . substr($path, 2);
            $this->logger?->debug('resolvePath tilde branch', ['home' => ($_SERVER['HOME'] ?? '/root'), 'result' => $result]);
            return $result;
        }
        if (!str_starts_with($path, '/')) {
            $result = dirname(__DIR__, 2) . '/' . $path;
            $this->logger?->debug('resolvePath relative branch', ['root' => dirname(__DIR__, 2), 'result' => $result]);
            return $result;
        }
        $this->logger?->debug('resolvePath absolute branch', ['result' => $path]);
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

        $this->logger?->debug('getEntries called', ['filePath' => $filePath, 'dirKey' => $dirKey, 'realPath' => $realPath]);

        // SSH — pliki już zweryfikowane przy pobieraniu
        if ($dirKey && str_starts_with($dirKey, 'ssh:')) {
            $allowed = $realPath !== false;
            $this->logger?->debug('getEntries ssh branch', ['filePath' => $filePath, 'realPath' => $realPath, 'allowed' => $allowed]);
        } else {
            $dirPath = $dirKey ? $this->resolveDirPath($dirKey) : null;
            $this->logger?->debug('getEntries dir resolved', ['dirKey' => $dirKey, 'dirPath' => $dirPath]);

            if ($dirPath) {
                $resolved = realpath($dirPath);
                $this->logger?->debug('getEntries dirPath resolved', ['dirPath' => $dirPath, 'resolved' => $resolved]);
                if ($realPath && $resolved && str_starts_with($realPath, $resolved)) {
                    $allowed = true;
                }
                $this->logger?->debug('getEntries path check', ['realPath' => $realPath, 'resolved' => $resolved, 'startsWith' => ($realPath && $resolved && str_starts_with($realPath, $resolved))]);
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
                $this->logger?->debug('getEntries fallback branch', ['dirCount' => count($dirs), 'allowed' => $allowed]);
            }
        }

        $this->logger?->debug('getEntries result', ['filePath' => $filePath, 'allowed' => $allowed]);

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