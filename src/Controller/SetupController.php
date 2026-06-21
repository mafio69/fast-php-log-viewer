<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Service\SetupWizard;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SetupController
{
    public function __construct(
        private readonly SetupWizard $wizard
    ) {
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $status = $this->wizard->getStatus();

        if ($status['state'] !== 'complete') {
            $status['setup_required'] = true;
        }

        $response->getBody()->write(json_encode($status));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function postStep(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $step = $data['step'] ?? null;
        $stepData = $data['data'] ?? [];
        $skip = $data['skip'] ?? false;

        if (!in_array($step, SetupWizard::STEPS, true)) {
            $response->getBody()->write(json_encode(['error' => 'unknown_step', 'step' => $step]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $result = $this->wizard->processStep($step, $stepData, $skip);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => 'unknown_step', 'step' => $step]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'internal_error', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function postMigrateSSH(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data) || !isset($data['connections'])) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $connections = $data['connections'];
        if (!is_array($connections)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_json']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->wizard->migrateSSHFromLocalStorage($connections);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
