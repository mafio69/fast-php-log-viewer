<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Exception;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogScanner;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DirectoryController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly LogConfig $logConfig,
        private readonly LogScanner $logScanner,
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
            return $this->json($response, ['error' => $e->getMessage()], 400);
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

    public function scanDirectories(Request $request, Response $response): Response
    {
        $dirs = $this->logScanner->scanCommonDirectories();
        return $this->json($response, $dirs);
    }
}
