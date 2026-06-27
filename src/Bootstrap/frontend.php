<?php

declare(strict_types=1);

use Mariusz\LogViewer\Bootstrap\AppBootstrap;
use Mariusz\LogViewer\Routing\LegacyRouter;
use Slim\Psr7\Factory\ServerRequestFactory;

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__.'/../../logs');
}
if (!defined('EDITOR_URL')) {
    define('EDITOR_URL', getenv('EDITOR_URL') ?: 'phpstorm://open?file={file}&line={line}');
}

// Handle API routes (Slim) or legacy ?action= compatibility, otherwise render HTML
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

$bootstrapSlim = function (): void {
    $app = AppBootstrap::create();
    $request = ServerRequestFactory::createFromGlobals();
    $app->run($request);
    exit;
};

// Handle ?action= legacy requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if (LegacyRouter::hasAction($action)) {
        $_SERVER['REQUEST_URI'] = LegacyRouter::rewriteRequestUri($action, $_GET);
        $bootstrapSlim();
    }
    $bootstrapSlim();
}

// Run Slim for all /api routes
if (str_starts_with($path, '/api')) {
    $bootstrapSlim();
}

// CSP nonce for inline script
$cspNonce = bin2hex(random_bytes(16));
define('CSP_NONCE', $cspNonce);

// Security headers for SPA
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header(
    "Content-Security-Policy: default-src 'self'; script-src 'self' cdn.jsdelivr.net 'nonce-$cspNonce' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self' cdn.jsdelivr.net"
);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Render SPA template
require __DIR__.'/../../templates/viewer.php';
