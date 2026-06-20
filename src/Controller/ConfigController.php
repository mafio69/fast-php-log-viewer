<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConfigController
{
    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $status = $this->configManager->getSetupStatus();
        $response->getBody()->write(json_encode($status));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getConfig(Request $request, Response $response): Response
    {
        $config = $this->configManager->getPublicConfig();
        $response->getBody()->write(json_encode($config));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateConfig(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid data']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $this->configManager->updateConfig($data);
        
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
