<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use DI\ContainerBuilder;
use Mariusz\LogViewer\Config\ConfigManager;
use Slim\App;
use Slim\Factory\AppFactory;

class AppBootstrap
{
    public static function create(): App
    {
        $containerBuilder = new ContainerBuilder();

        // Constants
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 2));
        }
        if (!defined('DATA_DIR')) {
            define('DATA_DIR', ROOT_DIR . '/data');
        }

        $containerBuilder->addDefinitions([
            ConfigManager::class => function () {
                return new ConfigManager(
                    DATA_DIR . '/app_config.json',
                    ROOT_DIR . '/.env'
                );
            },
            // You can add more services here later (e.g. SSH, LogParser)
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        $app = AppFactory::create();

        // Routing
        $app->group('/api', function ($group) {
            $group->get('/config/status', [\Mariusz\LogViewer\Controller\ConfigController::class, 'getStatus']);
            $group->get('/config', [\Mariusz\LogViewer\Controller\ConfigController::class, 'getConfig']);
            $group->post('/config', [\Mariusz\LogViewer\Controller\ConfigController::class, 'updateConfig']);
            $group->get('/config/generators/id', function ($request, $response, $args) {
                $cm = $this->get(\Mariusz\LogViewer\Config\ConfigManager::class);
                $response->getBody()->write(json_encode(['id' => $cm->generateInstallationId()]));
                return $response->withHeader('Content-Type', 'application/json');
            });
            $group->get('/config/generators/key', function ($request, $response, $args) {
                $cm = $this->get(\Mariusz\LogViewer\Config\ConfigManager::class);
                $response->getBody()->write(json_encode(['key' => $cm->generateEncryptionKey()]));
                return $response->withHeader('Content-Type', 'application/json');
            });
        });

        // Add standard Slim middleware
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        return $app;
    }
}
