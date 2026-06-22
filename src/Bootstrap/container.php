<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use DI\ContainerBuilder;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\AppConfigController;
use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Controller\SetupController;
use Mariusz\LogViewer\Controller\SSHController;
use Mariusz\LogViewer\Middleware\SetupMiddleware;
use Mariusz\LogViewer\Service\GlobLogFinder;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\SetupWizard;
use function DI\autowire;

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

        // Service Bindings
        LogFinderInterface::class => autowire(GlobLogFinder::class),

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
        SetupController::class => function ($c) {
            return new SetupController(
                $c->get(SetupWizard::class)
            );
        },

        // AppConfigController - wstrzykuje ConfigManager
        AppConfigController::class => function ($c) {
            return new AppConfigController(
                $c->get(ConfigManager::class)
            );
        },

        // LogController (nowy Slim) - wstrzykuje LogConfig, ConfigManager i LogFinderInterface
        LogController::class => function ($c) {
            return new LogController(
                $c->get(LogConfig::class),
                $c->get(ConfigManager::class),
                $c->get(LogFinderInterface::class)
            );
        },

        // DirectoryController - wstrzykuje LogConfig
        DirectoryController::class => function ($c) {
            return new DirectoryController(
                $c->get(LogConfig::class)
            );
        },

        // SSHController - brak zależności
        SSHController::class => function ($c) {
            return new SSHController();
        },

        // SetupMiddleware - wstrzykuje ConfigManager
        SetupMiddleware::class => function ($c) {
            return new SetupMiddleware(
                $c->get(ConfigManager::class)
            );
        },
    ]);
};