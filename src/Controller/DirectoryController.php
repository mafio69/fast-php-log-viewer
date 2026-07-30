<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Exception;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\Docker\DockerLogSourceCollector;
use Mariusz\LogViewer\Service\LogScanner;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DirectoryController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly LogScanner $logScanner,
        private readonly LogSourceCollectorInterface $sourceCollector,
        private readonly ?DockerLogSourceCollector $dockerSourceCollector = null,
    ) {
    }

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        try {
            $id = $this->logConfig->addDirectory($data);
            return $this->json($response, ['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            error_log('DirectoryController: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Nie można dodać katalogu.'], 400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $result = $this->logConfig->updateDirectory($id, $data);
        return $this->json($response, ['success' => $result]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $result = $this->logConfig->deleteDirectory($id);
        return $this->json($response, ['success' => $result]);
    }

    public function getDefaultDirectories(Request $request, Response $response): Response
    {
        $dirs = LogConfig::getDefaultDirectories();
        return $this->json($response, $dirs);
    }

    public function getDeferredDirectories(Request $request, Response $response): Response
    {
        return $this->json($response, $this->logConfig->getDeferredDirectories());
    }

    public function scanDirectories(Request $request, Response $response): Response
    {
        // Two-step pipeline: Collector gathers which directories to look at,
        // LogScanner reads the file list of each one. Keeping "collect" and
        // "scan" separate means this controller orchestrates — neither job
        // lives inside the other.
        $sources = $this->sourceCollector->collect();

        if ($this->dockerSourceCollector !== null) {
            $dockerSources = $this->dockerSourceCollector->collect();
            $sources = array_merge($sources, $dockerSources);
        }

        $foundDirs = [];

        foreach ($sources as $source) {
            $files = $this->logScanner->scanDirectory($source->path);
            if (!empty($files)) {
                $foundDirs[$source->path] = [
                    'path' => $source->path,
                    'name' => basename($source->path),
                    'type' => $source->type,
                    'file_count' => count($files),
                    'files' => array_slice($files, 0, 10), // First 10 files
                ];
            }
        }

        return $this->json($response, $foundDirs);
    }
}
