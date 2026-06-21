<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Setup Wizard
    $app->get('/api/setup/status', [\Mariusz\LogViewer\Controller\SetupController::class, 'getStatus']);
    $app->post('/api/setup/step', [\Mariusz\LogViewer\Controller\SetupController::class, 'postStep']);
    $app->post('/api/setup/migrate-ssh', [\Mariusz\LogViewer\Controller\SetupController::class, 'postMigrateSSH']);

    // App Config
    $app->get('/api/app-config', [\Mariusz\LogViewer\Controller\AppConfigController::class, 'getConfig']);
    $app->post('/api/app-config', [\Mariusz\LogViewer\Controller\AppConfigController::class, 'patchConfig']);

    // Log API (chronione przez SetupMiddleware)
    $app->get('/api/directories', [\Mariusz\LogViewer\Controller\LogController::class, 'getDirectories']);
    $app->get('/api/files', [\Mariusz\LogViewer\Controller\LogController::class, 'getFiles']);
    $app->get('/api/entries', [\Mariusz\LogViewer\Controller\LogController::class, 'getEntries']);

    // Directory Config
    $app->post('/api/config/directories', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'add']);
    $app->put('/api/config/directories/{id}', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'update']);
    $app->delete('/api/config/directories/{id}', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'delete']);
    $app->post('/api/config/cleanup-duplicates', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'cleanupDuplicates']);
    $app->post('/api/config/cleanup-allowed', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'cleanupAllowed']);

    // Scan
    $app->get('/api/scan/directories', [\Mariusz\LogViewer\Controller\DirectoryController::class, 'scanDirectories']);

    // SSH
    $app->post('/api/ssh/test-connection', [\Mariusz\LogViewer\Controller\SSHController::class, 'testConnection']);
    $app->post('/api/ssh/list-files', [\Mariusz\LogViewer\Controller\SSHController::class, 'listFiles']);
    $app->post('/api/ssh/read-file', [\Mariusz\LogViewer\Controller\SSHController::class, 'readFile']);
    $app->post('/api/ssh/download-file', [\Mariusz\LogViewer\Controller\SSHController::class, 'downloadFile']);
};
