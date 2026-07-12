<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Exception;
use InvalidArgumentException;
use Mariusz\LogViewer\Service\SetupWizard;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SetupController
{
    use JsonResponseTrait;

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

        return $this->json($response, $status);
    }

    public function postStep(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $step = $data['step'] ?? null;
        $stepData = $data['data'] ?? [];
        $skip = $data['skip'] ?? false;

        if (!in_array($step, SetupWizard::STEPS, true)) {
            return $this->json($response, ['error' => 'unknown_step', 'step' => $step], 400);
        }

        try {
            $result = $this->wizard->processStep($step, $stepData, $skip);
            return $this->json($response, $result);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['error' => 'unknown_step', 'step' => $step], 400);
        } catch (Exception $e) {
            return $this->json($response, ['error' => 'internal_error', 'message' => $e->getMessage()], 500);
        }
    }

    public function postMigrateSSH(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data) || !isset($data['connections'])) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $connections = $data['connections'];
        if (!is_array($connections)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $result = $this->wizard->migrateSSHFromLocalStorage($connections);
        return $this->json($response, $result);
    }
}
