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
    use JsonResponseTrait;

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
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'ssh_extension_missing'], 503);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();
            $ssh->disconnect();
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function listFiles(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'ssh_extension_missing'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'missing_path'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $finder = new RemoteLogFinder($ssh);
            $files = $finder->findAll($path);

            $ssh->disconnect();

            return $this->json($response, ['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function readFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'ssh_extension_missing'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'missing_path'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $content = $ssh->readFile($path);
            $entries = $this->logParser->parseString($content);

            $ssh->disconnect();

            return $this->json($response, ['success' => true, 'entries' => $entries]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function downloadFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'ssh_extension_missing'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'missing_path'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $fileSize = $ssh->fileSize($path);
            if ($fileSize > 10 * 1024 * 1024) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'file_too_large'], 400);
            }

            $content = $ssh->readFile($path);

            if ($this->securityService->isBinaryContent($content)) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'binary_content_not_allowed'], 400);
            }

            if ($this->securityService->containsSuspiciousContent($content)) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'suspicious_content_detected'], 400);
            }

            $ssh->disconnect();

            $dataDir = defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__, 2) . '/data';
            $localPath = $dataDir . '/downloaded_' . bin2hex(random_bytes(8)) . '.log';
            file_put_contents($localPath, $content);

            return $this->json($response, [
                'success' => true,
                'localPath' => $localPath,
                'size' => strlen($content)
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }
}
