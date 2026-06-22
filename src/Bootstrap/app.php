<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use Mariusz\LogViewer\Middleware\SetupMiddleware;
use Slim\App;
use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

return function (): App {
    $containerBuilder = new ContainerBuilder();

    // Load container definitions
    $containerDefinitions = require __DIR__ . '/container.php';
    $containerDefinitions($containerBuilder);

    $container = $containerBuilder->build();
    AppFactory::setContainer($container);

    $app = AppFactory::create();

    // Load routes FIRST
    $routes = require __DIR__.'/routes.php';
    $routes($app);

    // Add middleware AFTER routing (reverse order of execution)
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(true, true, true);

    // Add SetupMiddleware LAST (executes FIRST)
    $app->add(SetupMiddleware::class);

    return $app;
};
