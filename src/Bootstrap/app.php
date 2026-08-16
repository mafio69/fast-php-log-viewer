<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use DI\ContainerBuilder;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Middleware\AuthMiddleware;
use Mariusz\LogViewer\Middleware\SetupMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

return function (): App {
    $containerBuilder = new ContainerBuilder();

    $containerDefinitions = require __DIR__ . '/container.php';
    $containerDefinitions($containerBuilder);

    $container = $containerBuilder->build();
    AppFactory::setContainer($container);

    $configManager = $container->get(ConfigManager::class);
    $logConfig = $container->get(LogConfig::class);
    $config = $configManager->getConfig();
    if (!empty($config['ssh_profiles']) && !$logConfig->hasSSHConnections()) {
        $logConfig->migrateSSHProfilesFromConfig($config['ssh_profiles']);
        $configManager->clearSSHProfiles();
    }

    $app = AppFactory::create();

    $routes = require __DIR__ . '/routes.php';
    $routes($app);

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $errorMiddleware = $app->addErrorMiddleware(false, true, false);
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (ServerRequestInterface $request, \Throwable $exception, bool $displayErrorDetails) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Strona nie istnieje. Może kot ją zjadł? 🐱']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    );

    $app->add(SetupMiddleware::class);
    $app->add(AuthMiddleware::class);

    return $app;
};
