<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use function DI\autowire;

use DI\ContainerBuilder;
use Mariusz\Logger\DualLogger;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Controller\AllowedContainerController;
use Mariusz\LogViewer\Controller\AllowedContainerPathController;
use Mariusz\LogViewer\Controller\AppConfigController;
use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Controller\SetupController;
use Mariusz\LogViewer\Controller\SSHController;
use Mariusz\LogViewer\Middleware\SetupMiddleware;
use Mariusz\LogViewer\Service\Docker\DockerDirectoryReader;
use Mariusz\LogViewer\Service\Docker\DockerLogSourceCollector;
use Mariusz\LogViewer\Service\DockerExecService;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\Host\HostLogSourceCollector;
use Mariusz\LogViewer\Service\Host\LocalDirectoryReader;
use Mariusz\LogViewer\Service\Host\LocalFileReader;
use Mariusz\LogViewer\Service\LogFinderInterface;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Service\LogScanner;
use Mariusz\LogViewer\Service\LogSourceCollectorInterface;
use Mariusz\LogViewer\Service\PathResolver;
use Mariusz\LogViewer\Service\SecurityService;
use Mariusz\LogViewer\Service\SetupWizard;
use Mariusz\LogViewer\Service\Ssh\SshLogSourceCollector;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

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
        LogFinderInterface::class => autowire(LocalDirectoryReader::class),
        // LogSourceCollector: HostLogSourceCollector (host defaults + LogConfig custom dirs)
        LogSourceCollectorInterface::class => autowire(HostLogSourceCollector::class),

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

        // LogController - wstrzykuje LogConfig, ConfigManager, LogFinder, PathResolver, FileAccessValidator, LogParser, LocalFileReader, DockerExecService, DockerDirectoryReader
        LogController::class => function ($c) {
            return new LogController(
                $c->get(LogConfig::class),
                $c->get(ConfigManager::class),
                $c->get(LogFinderInterface::class),
                $c->get(PathResolver::class),
                $c->get(FileAccessValidator::class),
                $c->get(LogParser::class),
                $c->get(LocalFileReader::class),
                $c->get(DockerExecService::class),
                $c->get(DockerDirectoryReader::class),
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

        // LogScanner - domyslne sciezki z DefaultLogSources::DEFAULTS (constructor default)
        LogScanner::class => function () {
            return new LogScanner();
        },

        // SecurityService - brak zależności
        SecurityService::class => function () {
            return new SecurityService();
        },

        // DockerExecService - allow-listy kontenerow i sciezek to suma env (ustawiane raz, np.
        // w produkcji) i tego, co user dopisal w runtime przez UI (przechowywane w LogConfig -
        // patrz AllowedContainerController / AllowedContainerPathController), zeby dodanie
        // kontenera lub sciezki nie wymagalo edycji .env ani restartu.
        DockerExecService::class => function ($c) {
            $logConfig = $c->get(LogConfig::class);

            $containers = array_values(array_unique(array_merge(
                DockerExecService::containersFromEnv(),
                $logConfig->getAllowedContainers()
            )));
            $paths = array_values(array_unique(array_merge(
                DockerExecService::defaultPathPrefixes(),
                $logConfig->getAllowedContainerPaths()
            )));

            return new DockerExecService($containers, $paths);
        },

        // DockerDirectoryReader — deleguje listFiles do DockerExecService (socket)
        DockerDirectoryReader::class => function ($c) {
            return new DockerDirectoryReader($c->get(DockerExecService::class));
        },

        // DockerLogSourceCollector — zbiera dockerowe LogSource-y z LogConfig
        DockerLogSourceCollector::class => function ($c) {
            return new DockerLogSourceCollector($c->get(LogConfig::class));
        },

        // SshLogSourceCollector — zbiera ssh-owe LogSource-y z LogConfig
        SshLogSourceCollector::class => function ($c) {
            return new SshLogSourceCollector($c->get(LogConfig::class));
        },

        // DirectoryController - wstrzykuje LogConfig, LogScanner, LogSourceCollectorInterface, DockerLogSourceCollector
        DirectoryController::class => function ($c) {
            return new DirectoryController(
                $c->get(LogConfig::class),
                $c->get(LogScanner::class),
                $c->get(LogSourceCollectorInterface::class),
                $c->get(DockerLogSourceCollector::class),
            );
        },

        // AllowedContainerController - wstrzykuje LogConfig
        AllowedContainerController::class => function ($c) {
            return new AllowedContainerController($c->get(LogConfig::class));
        },

        // AllowedContainerPathController - wstrzykuje LogConfig
        AllowedContainerPathController::class => function ($c) {
            return new AllowedContainerPathController($c->get(LogConfig::class));
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
