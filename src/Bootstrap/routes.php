<?php

declare(strict_types=1);

use Mariusz\LogViewer\Controller\AllowedContainerController;
use Mariusz\LogViewer\Controller\AppConfigController;
use Mariusz\LogViewer\Controller\DirectoryController;
use Mariusz\LogViewer\Controller\LogController;
use Mariusz\LogViewer\Controller\SetupController;
use Mariusz\LogViewer\Controller\SSHController;
use Slim\App;

return function (App $app): void {
    // Setup Wizard
    $app->get('/api/setup/status', [SetupController::class, 'getStatus']);
    $app->post('/api/setup/step', [SetupController::class, 'postStep']);
    $app->post('/api/setup/migrate-ssh', [SetupController::class, 'postMigrateSSH']);

    // App Config
    $app->get('/api/app-config', [AppConfigController::class, 'getConfig']);
    $app->post('/api/app-config', [AppConfigController::class, 'patchConfig']);

    // Default directories (source of truth, no DB needed)
    $app->get('/api/config/default-directories', [DirectoryController::class, 'getDefaultDirectories']);

    // Log API (chronione przez SetupMiddleware)
    $app->get('/api/directories', [LogController::class, 'getDirectories']);
    $app->get('/api/files', [LogController::class, 'getFiles']);
    $app->get('/api/entries', [LogController::class, 'getEntries']);

    // Directory Config
    $app->post('/api/config/directories', [DirectoryController::class, 'add']);
    $app->put('/api/config/directories/{id}', [DirectoryController::class, 'update']);
    $app->delete('/api/config/directories/{id}', [DirectoryController::class, 'delete']);
    $app->get('/api/config/directories/deferred', [DirectoryController::class, 'getDeferredDirectories']);

    // Scan
    $app->get('/api/scan/directories', [DirectoryController::class, 'scanDirectories']);

    // Allowed containers (runtime-editable, on top of LOG_VIEWER_ALLOWED_CONTAINERS).
    // Deliberately no PUT/edit - the only way to add a container is by actually
    // browsing it (see the container_not_allowed confirm flow in store.js);
    // this management view can only review the list and remove entries.
    $app->get('/api/config/allowed-containers', [AllowedContainerController::class, 'list']);
    $app->post('/api/config/allowed-containers', [AllowedContainerController::class, 'add']);
    $app->delete('/api/config/allowed-containers/{id}', [AllowedContainerController::class, 'delete']);

    // SSH
    $app->post('/api/ssh/test-connection', [SSHController::class, 'testConnection']);
    $app->post('/api/ssh/list-files', [SSHController::class, 'listFiles']);
    $app->post('/api/ssh/read-file', [SSHController::class, 'readFile']);
    $app->post('/api/ssh/download-file', [SSHController::class, 'downloadFile']);
};
