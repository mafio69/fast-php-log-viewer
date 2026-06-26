<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\PathResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogController
{
    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly ConfigManager $configManager,
        private readonly LogFinderInterface $logFinder,
        private readonly PathResolver $pathResolver,
        private readonly FileAccessValidator $accessValidator,
        private readonly LogParser $logParser,
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
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'server_error', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function getEntries(Request $request, Response $response): Response
    {
        $filePath = $request->getQueryParams()['file'] ?? null;
        if (!$filePath) {
            $response->getBody()->write(json_encode(['error' => 'missing_file']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $dirKey = $request->getQueryParams()['dir'] ?? null;

        if (!$this->accessValidator->isFileAllowed($filePath, $dirKey)) {
            $response->getBody()->write(json_encode(['error' => 'access_denied']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!file_exists($filePath)) {
            $response->getBody()->write(json_encode(['error' => 'file_not_found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $level = $request->getQueryParams()['level'] ?? null;
        $entries = $this->logParser->parseFile($filePath);

        if ($level) {
            $entries = array_values(array_filter($entries, fn($e) => strtoupper($e['level']) === strtoupper($level)));
        }

        $response->getBody()->write(json_encode($entries));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
