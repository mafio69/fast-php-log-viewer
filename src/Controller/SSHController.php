<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\RemoteLogFinder;
use Mariusz\LogViewer\Service\SSH;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SSHController
{
    public function __construct() {
    }

    public function testConnection(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $ssh = new SSH($data);
            $ssh->connect();
            $ssh->disconnect();
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function listFiles(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            $response->getBody()->write(json_encode(['error' => 'missing_path']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $ssh = new SSH($data);
            $ssh->connect();

            $finder = new RemoteLogFinder($ssh);
            $files = $finder->findAll($path);

            $ssh->disconnect();
            
            $response->getBody()->write(json_encode(['success' => true, 'files' => $files]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function readFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            $response->getBody()->write(json_encode(['error' => 'missing_path']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $ssh = new SSH($data);
            $ssh->connect();
            
            $content = $ssh->readFile($path);
            $parser = new LogParser();
            $entries = $parser->parseString($content);
            
            $ssh->disconnect();
            
            $response->getBody()->write(json_encode(['success' => true, 'entries' => $entries]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function downloadFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            $response->getBody()->write(json_encode(['error' => 'missing_path']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $ssh = new SSH($data);
            $ssh->connect();
            
            // Walidacja bezpieczeństwa - rozmiar pliku
            $fileSize = $ssh->fileSize($path);
            if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'file_too_large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Pobierz zawartość
            $content = $ssh->readFile($path);
            
            // Walidacja bezpieczeństwa - zawartość binarna
            if (\Mariusz\LogViewer\Service\SecurityService::isBinaryContent($content)) {
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'binary_content_not_allowed']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Walidacja bezpieczeństwa - suspicious content
            if (\Mariusz\LogViewer\Service\SecurityService::containsSuspiciousContent($content)) {
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'suspicious_content_detected']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $ssh->disconnect();
            
            // Zapisz lokalnie
            $localPath = DATA_DIR . '/downloaded_' . bin2hex(random_bytes(8)) . '.log';
            file_put_contents($localPath, $content);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'localPath' => $localPath,
                'size' => strlen($content)
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
