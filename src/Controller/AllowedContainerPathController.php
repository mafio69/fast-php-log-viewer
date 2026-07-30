<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\LogConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AllowedContainerPathController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly LogConfig $logConfig,
    ) {
    }

    public function list(Request $request, Response $response): Response
    {
        return $this->json($response, $this->logConfig->getAllowedContainerPathsDetailed());
    }

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $prefix = is_array($data) ? trim((string)($data['path_prefix'] ?? '')) : '';

        if ($prefix === '' || !str_starts_with($prefix, '/') || str_contains($prefix, "\0") || str_contains($prefix, "\n")) {
            return $this->json($response, ['error' => 'Nieprawidłowa ścieżka.'], 400);
        }

        $this->logConfig->addAllowedContainerPath($prefix);

        return $this->json($response, ['success' => true]);
    }

    /**
     * @param array<string, string> $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $result = $this->logConfig->deleteAllowedContainerPath($id);

        return $this->json($response, ['success' => $result]);
    }
}
