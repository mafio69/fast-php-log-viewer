<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Controller;

use Psr\Http\Message\ResponseInterface as Response;

trait JsonResponseTrait
{
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
