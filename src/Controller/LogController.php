<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\DockerExecService;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\PathResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ConfigManager $configManager,
        private readonly LogFinderInterface $logFinder,
        private readonly PathResolver $pathResolver,
        private readonly FileAccessValidator $accessValidator,
        private readonly LogParser $logParser,
        private readonly ?DockerExecService $dockerExec = null,
    ) {
    }

    public function getDirectories(Request $request, Response $response): Response
    {
        $this->logConfig->cleanupAuto();
        $dirs = $this->logConfig->getValidDirectories();

        if (!$this->configManager->isSshEnabled()) {
            $dirs = array_filter($dirs, fn($d) => ($d['type'] ?? 'local') !== 'ssh');
        }

        return $this->json($response, array_values($dirs));
    }

    public function getFiles(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $path = $params['path'] ?? null;
            $dirKey = $params['dir'] ?? null;

            if ($path) {
                $absPath = $this->pathResolver->resolvePath($path);
                $files = $this->logFinder->findAll($absPath);
                $basePath = rtrim($absPath, '/');
                $result = array_map(fn($f) => [
                    'file' => $basePath . '/' . $f['file'],
                    'date' => $f['date'],
                    'size' => $f['size'],
                ], $files);
                return $this->json($response, $result);
            }

            if (!$dirKey) {
                return $this->json($response, ['error' => 'missing_dir'], 400);
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
                return $this->json($response, ['error' => 'directory_not_found'], 404);
            }

            $files = $this->logFinder->findAll($dir['path']);
            $basePath = rtrim($dir['path'], '/');
            $result = array_map(fn($f) => [
                'file' => $basePath . '/' . $f['file'],
                'date' => $f['date'],
                'size' => $f['size'],
            ], $files);

            return $this->json($response, $result);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getEntries(Request $request, Response $response): Response
    {
        $filePath = $request->getQueryParams()['file'] ?? null;
        if (!$filePath) {
            return $this->json($response, ['error' => 'missing_file'], 400);
        }

        $containerId = $request->getQueryParams()['container_id'] ?? null;

        if ($containerId !== null) {
            return $this->getEntriesFromContainer($containerId, $filePath, $request, $response);
        }

        $dirKey = $request->getQueryParams()['dir'] ?? null;

        if (!$this->accessValidator->isFileAllowed($filePath, $dirKey)) {
            // Direct file path (no dirKey): auto-add parent directory to allowed dirs
            if ($dirKey === null && str_starts_with($filePath, '/')) {
                $parentDir = $this->autoRegisterParentDir($filePath);
                if ($parentDir !== null && $this->accessValidator->isFileAllowed($filePath, $dirKey)) {
                    // Falls through — continue to parseFile
                } else {
                    return $this->json($response, ['error' => 'access_denied'], 403);
                }
            } else {
                return $this->json($response, ['error' => 'access_denied'], 403);
            }
        }

        if (!file_exists($filePath)) {
            return $this->json($response, ['error' => 'file_not_found'], 404);
        }

        $level = $request->getQueryParams()['level'] ?? null;
        $entries = $this->logParser->parseFile($filePath);

        if ($level) {
            $entries = array_values(array_filter($entries, fn($e) => strtoupper($e['level']) === strtoupper($level)));
        }

        return $this->json($response, $entries);
    }

    private function getEntriesFromContainer(string $containerId, string $filePath, Request $request, Response $response): Response
    {
        if (!$this->dockerExec || !$this->dockerExec->isAvailable()) {
            return $this->json($response, ['error' => 'docker_unavailable'], 503);
        }

        try {
            $content = $this->dockerExec->readFile($containerId, $filePath);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'file_not_found' || $message === 'container_not_found') {
                return $this->json($response, ['error' => $message], 404);
            }
            return $this->json($response, ['error' => 'docker_exec_failed', 'message' => $message], 500);
        }

        try {
            $entries = $this->logParser->parseString($content);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'parse_error', 'message' => $e->getMessage()], 500);
        }

        $level = $request->getQueryParams()['level'] ?? null;
        if ($level) {
            $entries = array_values(array_filter($entries, fn($e) => strtoupper($e['level']) === strtoupper($level)));
        }

        return $this->json($response, $entries);
    }

    private function autoRegisterParentDir(string $filePath): ?string
    {
        $blockedDirs = ['/etc', '/root', '/proc', '/sys', '/dev'];
        foreach ($blockedDirs as $blocked) {
            if (str_starts_with($filePath, $blocked)) {
                return null;
            }
        }

        $parentDir = dirname($filePath);
        if ($parentDir === '.' || $parentDir === '/') {
            return null;
        }

        $this->logConfig->addDirectory([
            'name' => 'local:' . $parentDir,
            'path' => $parentDir,
            'type' => 'local',
        ]);

        return $parentDir;
    }
}
