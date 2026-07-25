<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\LogConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AllowedContainerController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly LogConfig $logConfig,
    ) {
    }

    public function list(Request $request, Response $response): Response
    {
        return $this->json($response, $this->logConfig->getAllowedContainersDetailed());
    }

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $containerId = is_array($data) ? trim((string)($data['container_id'] ?? '')) : '';

        if ($containerId === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/', $containerId)) {
            return $this->json($response, ['error' => 'invalid_container_id'], 400);
        }

        $this->logConfig->addAllowedContainer($containerId);

        return $this->json($response, ['success' => true]);
    }

    /**
     * @param array<string, string> $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $result = $this->logConfig->deleteAllowedContainer($id);

        return $this->json($response, ['success' => $result]);
    }
}
