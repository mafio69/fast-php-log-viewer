<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Config\ConfigManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AppConfigController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    public function getConfig(Request $request, Response $response): Response
    {
        $config = $this->configManager->getPublicConfig();
        return $this->json($response, $config);
    }

    public function patchConfig(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $this->configManager->updateConfig($data);
        return $this->json($response, ['success' => true]);
    }
}
