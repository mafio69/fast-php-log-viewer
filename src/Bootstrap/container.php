<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use DI\ContainerBuilder;
use Mariusz\Logger\DualLogger;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\AppConfigController;
use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Controller\SetupController;
use Mariusz\LogViewer\Controller\SSHController;
use Mariusz\LogViewer\Middleware\SetupMiddleware;
use Mariusz\LogViewer\Service\DockerExecService;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\GlobLogFinder;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\LogScanner;
use Mariusz\LogViewer\Service\PathResolver;
use Mariusz\LogViewer\Service\SecurityService;
use Mariusz\LogViewer\Service\SetupWizard;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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

        // Logger (debug + info + warning + error via DualLogger)
        LoggerInterface::class => DualLogger::create(DATA_DIR, LogLevel::DEBUG),

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

        // LogController - wstrzykuje LogConfig, ConfigManager, LogFinder, PathResolver, FileAccessValidator, LogParser, DockerExecService
        LogController::class => function ($c) {
            return new LogController(
                $c->get(LogConfig::class),
                $c->get(ConfigManager::class),
                $c->get(LogFinderInterface::class),
                $c->get(PathResolver::class),
                $c->get(FileAccessValidator::class),
                $c->get(LogParser::class),
                $c->get(DockerExecService::class)
            );
        },

        // PathResolver - wstrzykuje LogConfig i Logger
        PathResolver::class => function ($c) {
            return new PathResolver(
                $c->get(LogConfig::class),
                $c->get(LoggerInterface::class)
            );
        },

        // FileAccessValidator - wstrzykuje PathResolver, LogConfig i Logger
        FileAccessValidator::class => function ($c) {
            return new FileAccessValidator(
                $c->get(PathResolver::class),
                $c->get(LogConfig::class),
                $c->get(LoggerInterface::class)
            );
        },

        // LogParser - brak zależności
        LogParser::class => function () {
            return new LogParser();
        },

        // LogScanner - brak zależności
        LogScanner::class => function () {
            return new LogScanner();
        },

        // SecurityService - brak zależności
        SecurityService::class => function () {
            return new SecurityService();
        },

        // DockerExecService - brak zależności
        DockerExecService::class => function () {
            return new DockerExecService();
        },

        // DirectoryController - wstrzykuje LogConfig i LogScanner
        DirectoryController::class => function ($c) {
            return new DirectoryController(
                $c->get(LogConfig::class),
                $c->get(LogScanner::class)
            );
        },

        // SSHController - wstrzykuje LogParser i SecurityService
        SSHController::class => function ($c) {
            return new SSHController(
                $c->get(LogParser::class),
                $c->get(SecurityService::class)
            );
        },

        // SetupMiddleware - wstrzykuje ConfigManager
        SetupMiddleware::class => function ($c) {
            return new SetupMiddleware(
                $c->get(ConfigManager::class)
            );
        },
    ]);
};