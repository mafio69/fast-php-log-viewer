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
    public function __construct(
        private readonly LogConfig $logConfig
    ) {
    }

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $id = $this->logConfig->addDirectory($data);
            $response->getBody()->write(json_encode(['success' => true, 'id' => $id]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->logConfig->updateDirectory($id, $data);
        $response->getBody()->write(json_encode(['success' => $result]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $result = $this->logConfig->deleteDirectory($id);
        $response->getBody()->write(json_encode(['success' => $result]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getDefaultDirectories(Request $request, Response $response): Response
    {
        $dirs = LogConfig::getDefaultDirectories();
        $response->getBody()->write(json_encode($dirs));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function scanDirectories(Request $request, Response $response): Response
    {
        $scanner = new LogScanner();
        $dirs = $scanner->scanCommonDirectories();
        $response->getBody()->write(json_encode($dirs));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
