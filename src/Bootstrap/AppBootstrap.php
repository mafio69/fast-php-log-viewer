<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use Slim\App;
use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

class AppBootstrap
{
    public static function create(): App
    {
        $containerBuilder = new ContainerBuilder();

        // Load container definitions
        $containerDefinitions = require __DIR__ . '/container.php';
        $containerDefinitions($containerBuilder);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        $app = AppFactory::create();

        // Add middleware BEFORE routing
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);

        // Add SetupMiddleware
        $app->add(\Mariusz\LogViewer\Middleware\SetupMiddleware::class);

        // Load routes
        $routes = require __DIR__ . '/routes.php';
        $routes($app);

        return $app;
    }
}