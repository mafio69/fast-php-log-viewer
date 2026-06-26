<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Exception;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\RemoteLogFinder;
use Mariusz\LogViewer\Service\SecurityService;
use Mariusz\LogViewer\Service\SSH;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SSHController
{
    public function __construct(
        private readonly LogParser $logParser,
        private readonly SecurityService $securityService,
    ) {
    }

    private function extractSSHData(array $data): array
    {
        return [
            'ssh_host' => $data['ssh_host'] ?? '',
            'ssh_user' => $data['ssh_user'] ?? '',
            'ssh_port' => $data['ssh_port'] ?? 22,
            'ssh_auth_method' => $data['ssh_auth_method'] ?? 'password',
            'ssh_password' => $data['ssh_password'] ?? null,
            'ssh_key_path' => $data['ssh_key_path'] ?? null,
            'ssh_key_passphrase' => $data['ssh_key_passphrase'] ?? null,
        ];
    }

    public function testConnection(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();
            $ssh->disconnect();
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
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
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $finder = new RemoteLogFinder($ssh);
            $files = $finder->findAll($path);

            $ssh->disconnect();

            $response->getBody()->write(json_encode(['success' => true, 'files' => $files]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
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
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $content = $ssh->readFile($path);
            $entries = $this->logParser->parseString($content);

            $ssh->disconnect();

            $response->getBody()->write(json_encode(['success' => true, 'entries' => $entries]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
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
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $fileSize = $ssh->fileSize($path);
            if ($fileSize > 10 * 1024 * 1024) {
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'file_too_large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $content = $ssh->readFile($path);

            if ($this->securityService->isBinaryContent($content)) {
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'binary_content_not_allowed']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if ($this->securityService->containsSuspiciousContent($content)) {
                $ssh->disconnect();
                $response->getBody()->write(json_encode(['error' => 'suspicious_content_detected']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $ssh->disconnect();

            $dataDir = defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__, 2) . '/data';
            $localPath = $dataDir . '/downloaded_' . bin2hex(random_bytes(8)) . '.log';
            file_put_contents($localPath, $content);

            $response->getBody()->write(json_encode([
                'success' => true,
                'localPath' => $localPath,
                'size' => strlen($content)
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
