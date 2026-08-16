<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Mariusz\LogViewer\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class AuthController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $username = (string)($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->json($response, ['error' => 'Nazwa użytkownika i hasło są wymagane.'], 400);
        }

        try {
            $user = $this->authService->register($username, $password);
            return $this->json($response, ['success' => true, 'user' => $user]);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $username = (string)($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');

        $user = $this->authService->login($username, $password);
        if ($user === null) {
            return $this->json($response, ['error' => 'Nieprawidłowa nazwa użytkownika lub hasło.'], 401);
        }

        return $this->json($response, ['success' => true, 'user' => $user]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();
        return $this->json($response, ['success' => true]);
    }

    public function session(Request $request, Response $response): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            return $this->json($response, ['authenticated' => false]);
        }

        return $this->json($response, ['authenticated' => true, 'user' => $user]);
    }
}
