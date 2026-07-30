<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Exception;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\SecurityService;
use Mariusz\LogViewer\Service\SSH;
use Mariusz\LogViewer\Service\Ssh\SshDirectoryReader;
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
            return $this->json($response, ['error' => 'Rozszerzenie SSH2 nie jest dostępne.'], 503);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();
            $ssh->disconnect();
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            error_log('SSHController: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Wystąpił błąd połączenia SSH.'], 500);
        }
    }

    public function listFiles(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'Rozszerzenie SSH2 nie jest dostępne.'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'Nie podano ścieżki.'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $finder = new SshDirectoryReader($ssh);
            $files = $finder->findAll($path);

            $ssh->disconnect();

            return $this->json($response, ['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            error_log('SSHController: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Wystąpił błąd połączenia SSH.'], 500);
        }
    }

    public function readFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'Rozszerzenie SSH2 nie jest dostępne.'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'Nie podano ścieżki.'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $content = $ssh->readFile($path);
            $entries = $this->logParser->parseString($content);

            $ssh->disconnect();

            return $this->json($response, ['success' => true, 'entries' => $entries]);
        } catch (Exception $e) {
            error_log('SSHController: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Wystąpił błąd połączenia SSH.'], 500);
        }
    }

    public function downloadFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }
        if (!SSH::isAvailable()) {
            return $this->json($response, ['error' => 'Rozszerzenie SSH2 nie jest dostępne.'], 503);
        }

        $path = $data['path'] ?? '';
        if (empty($path)) {
            return $this->json($response, ['error' => 'Nie podano ścieżki.'], 400);
        }

        try {
            $ssh = new SSH($this->extractSSHData($data));
            $ssh->connect();

            $fileSize = $ssh->fileSize($path);
            if ($fileSize > 10 * 1024 * 1024) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'Plik jest za duży.'], 400);
            }

            $content = $ssh->readFile($path);

            if ($this->securityService->isBinaryContent($content)) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'Pliki binarne nie są obsługiwane.'], 400);
            }

            if ($this->securityService->containsSuspiciousContent($content)) {
                $ssh->disconnect();
                return $this->json($response, ['error' => 'Wykryto podejrzaną zawartość.'], 400);
            }

            $ssh->disconnect();

            $dataDir = defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__, 2) . '/data';
            $localPath = $dataDir . '/downloaded_' . bin2hex(random_bytes(8)) . '.log';
            file_put_contents($localPath, $content);

            return $this->json($response, [
                'success' => true,
                'localPath' => $localPath,
                'size' => strlen($content),
            ]);
        } catch (Exception $e) {
            error_log('SSHController: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Wystąpił błąd połączenia SSH.'], 500);
        }
    }
}
