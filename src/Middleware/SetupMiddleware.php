<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Middleware;

use Mariusz\LogViewer\Config\ConfigManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SetupMiddleware implements MiddlewareInterface
{
    private const PROTECTED_ROUTES = [
        '/api/directories',
        '/api/files',
        '/api/entries',
    ];

    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (in_array($path, self::PROTECTED_ROUTES, true) && !$this->configManager->isSetupComplete()) {
            $response = $handler->handle($request);
            $response->getBody()->write(json_encode(['error' => 'setup_required']));
            return $response->withStatus(503)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
