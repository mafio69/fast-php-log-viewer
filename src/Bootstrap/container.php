<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use DI\ContainerBuilder;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\SetupWizard;

return function (ContainerBuilder $containerBuilder): void {
    // Define constants as container parameters
    if (!defined('ROOT_DIR')) {
        define('ROOT_DIR', dirname(__DIR__, 2));
    }
    if (!defined('DATA_DIR')) {
        define('DATA_DIR', ROOT_DIR . '/data');
    }

    $containerBuilder->addDefinitions([
        // Parameters
        'root_dir' => ROOT_DIR,
        'data_dir' => DATA_DIR,

        // ConfigManager - singleton
        ConfigManager::class => function () {
            return new ConfigManager(
                DATA_DIR . '/app_config.json',
                ROOT_DIR . '/.env'
            );
        },

        // LogConfig - singleton
        LogConfig::class => function () {
            return new LogConfig(DATA_DIR . '/logviewer.db');
        },

        // SetupWizard - wstrzykuje ConfigManager i LogConfig
        SetupWizard::class => function ($c) {
            return new SetupWizard(
                $c->get(ConfigManager::class),
                $c->get(LogConfig::class)
            );
        },

        // SetupController - wstrzykuje SetupWizard
        \Mariusz\LogViewer\Controller\SetupController::class => function ($c) {
            return new \Mariusz\LogViewer\Controller\SetupController(
                $c->get(SetupWizard::class)
            );
        },

        // AppConfigController - wstrzykuje ConfigManager
        \Mariusz\LogViewer\Controller\AppConfigController::class => function ($c) {
            return new \Mariusz\LogViewer\Controller\AppConfigController(
                $c->get(ConfigManager::class)
            );
        },

        // LogController (nowy Slim) - wstrzykuje LogConfig i ConfigManager
        \Mariusz\LogViewer\Controller\LogController::class => function ($c) {
            return new \Mariusz\LogViewer\Controller\LogController(
                $c->get(LogConfig::class),
                $c->get(ConfigManager::class)
            );
        },

        // DirectoryController - wstrzykuje LogConfig
        \Mariusz\LogViewer\Controller\DirectoryController::class => function ($c) {
            return new \Mariusz\LogViewer\Controller\DirectoryController(
                $c->get(LogConfig::class)
            );
        },

        // SSHController - brak zależności
        \Mariusz\LogViewer\Controller\SSHController::class => function ($c) {
            return new \Mariusz\LogViewer\Controller\SSHController();
        },

        // SetupMiddleware - wstrzykuje ConfigManager
        \Mariusz\LogViewer\Middleware\SetupMiddleware::class => function ($c) {
            return new \Mariusz\LogViewer\Middleware\SetupMiddleware(
                $c->get(ConfigManager::class)
            );
        },
    ]);
};
