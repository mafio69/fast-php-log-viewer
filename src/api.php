<?php

declare(strict_types=1);

/**
 * fast-php-log-viewer API endpoint.
 *
 * GET ?action=files              → list of log files
 * GET ?action=entries&file=path  → parsed entries from a file
 *
 * Configure LOG_DIR before including or set it as a constant.
 */

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: dirname(__DIR__) . '/logs');
}

// When installed as a Composer dependency the autoloader is already loaded
// by the entry point (viewer/index.php). The original relative path
// __DIR__ . '/../vendor/autoload.php' resolves to the package's own vendor/
// which doesn't exist — only require if the file actually exists.
$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}
unset($_autoload);

use Mariusz\LogViewer\LogFinder;
use Mariusz\LogViewer\LogParser;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'files'   => respondFiles(),
        'entries' => respondEntries(),
        default   => respondError('Unknown action', 400),
    };
} catch (\Throwable $e) {
    respondError($e->getMessage(), 500);
}

function respondFiles(): void
{
    $finder = new LogFinder(LOG_DIR);
    $files  = $finder->findAll();

    echo json_encode(array_map(fn($f) => [
        'file' => $f['path'],
        'date' => $f['date'],
        'size' => $f['size'],
    ], $files));
}

function respondEntries(): void
{
    $file = $_GET['file'] ?? '';

    if ($file === '') {
        respondError('Missing file parameter', 400);
        return;
    }

    // Security: file must be inside LOG_DIR
    $real    = realpath($file);
    $logReal = realpath(LOG_DIR);

    // Normalize separators for Windows/WSL path compatibility
    $real    = $real    !== false ? str_replace('\\', '/', $real)    : str_replace('\\', '/', $file);
    $logReal = $logReal !== false ? str_replace('\\', '/', $logReal) : str_replace('\\', '/', LOG_DIR);

    if (!str_starts_with($real, rtrim($logReal, '/') . '/')) {
        respondError('Access denied', 403);
        return;
    }

    $parser  = new LogParser();
    $entries = $parser->parseFile($real);

    $level = $_GET['level'] ?? '';
    if ($level !== '') {
        $entries = array_values(array_filter($entries, fn($e) => $e['level'] === strtoupper($level)));
    }

    echo json_encode($entries);
}

function respondError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
}
