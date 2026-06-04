<?php

declare(strict_types=1);

/**
 * fast-php-log-viewer API endpoint.
 *
 * GET ?action=directories        → list of configured log directories
 * GET ?action=files[&dir=key]    → list of log files
 * GET ?action=entries&file=path  → parsed entries from a file
 *
 * Configure LOG_DIR (single) or LOG_DIRS (multiple) before including.
 */

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: dirname(__DIR__) . '/logs');
}

if (!defined('LOG_DIRS')) {
    define('LOG_DIRS', []);
}

$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}
unset($_autoload);

use Mariusz\LogViewer\LogFinder;
use Mariusz\LogViewer\LogParser;
use Mariusz\LogViewer\LogConfig;
use Mariusz\LogViewer\LogScanner;
use Mariusz\LogViewer\SSH;
use Mariusz\LogViewer\RemoteLogFinder;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'directories' => respondDirectories(),
        'files'       => respondFiles(),
        'entries'     => respondEntries(),
        'config-dirs' => respondConfigDirectories(),
        'config-add-dir' => respondAddDirectory(),
        'config-update-dir' => respondUpdateDirectory(),
        'config-delete-dir' => respondDeleteDirectory(),
        'config-init-defaults' => respondInitDefaults(),
        'scan-directories' => respondScanDirectories(),
        'ssh-test-connection' => respondTestSSHConnection(),
        'ssh-list-files' => respondSSHListFiles(),
        'ssh-read-file' => respondSSHReadFile(),
        default       => respondError('Unknown action', 400),
    };
} catch (Throwable $e) {
    respondError($e->getMessage(), 500);
}

function getLogDirs(): array
{
    $dirs = LOG_DIRS;
    if (empty($dirs)) {
        $dirs = ['default' => LOG_DIR];
    }
    return $dirs;
}

function resolveLogDir(): string
{
    $dirs = getLogDirs();
    $key  = $_GET['dir'] ?? array_key_first($dirs);
    return $dirs[$key] ?? reset($dirs);
}

/** @throws JsonException */
function respondDirectories(): void
{
    $dirs = getLogDirs();
    $result = [];
    foreach ($dirs as $key => $path) {
        $result[] = ['key' => $key, 'path' => $path];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondFiles(): void
{
    $logDir = resolveLogDir();
    $finder = new LogFinder($logDir);
    $files  = $finder->findAll();

    echo json_encode(array_map(static fn($f) => [
        'file' => $f['path'],
        'date' => $f['date'],
        'size' => $f['size'],
    ], $files), JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondEntries(): void
{
    $file = $_GET['file'] ?? '';

    if ($file === '') {
        respondError('Missing file parameter', 400);
        return;
    }

    // Security: file must be inside one of the configured log dirs
    $dirs = getLogDirs();
    $real = realpath($file);
    $real = $real !== false ? str_replace('\\', '/', $real) : str_replace('\\', '/', $file);

    $allowed = false;
    foreach ($dirs as $dir) {
        $logReal = realpath($dir);
        $logReal = $logReal !== false ? str_replace('\\', '/', $logReal) : str_replace('\\', '/', $dir);
        if (str_starts_with($real, rtrim($logReal, '/') . '/')) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        respondError('Access denied', 403);
        return;
    }

    $parser  = new LogParser();
    $entries = $parser->parseFile($real);

    $level = $_GET['level'] ?? '';
    if ($level !== '') {
        $entries = array_values(array_filter($entries, static fn($e) => $e['level'] === strtoupper($level)));
    }

    echo json_encode($entries, JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondConfigDirectories(): void
{
    $config = new LogConfig();
    $dirs = $config->getDirectories();
    echo json_encode($dirs, JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondAddDirectory(): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respondError('Invalid JSON input', 400);
        return;
    }

    $config = new LogConfig();
    $id = $config->addDirectory($input);
    echo json_encode(['success' => true, 'id' => $id], JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondUpdateDirectory(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id === 0) {
        respondError('Missing ID parameter', 400);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respondError('Invalid JSON input', 400);
        return;
    }

    $config = new LogConfig();
    $success = $config->updateDirectory($id, $input);
    echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondDeleteDirectory(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id === 0) {
        respondError('Missing ID parameter', 400);
        return;
    }

    $config = new LogConfig();
    $success = $config->deleteDirectory($id);
    echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondInitDefaults(): void
{
    $config = new LogConfig();
    $config->addDefaultDirectories();
    echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondScanDirectories(): void
{
    $scanner = new LogScanner();
    $found = $scanner->scanCommonDirectories();
    echo json_encode($found, JSON_THROW_ON_ERROR);
}

/** @throws JsonException */
function respondTestSSHConnection(): void
{
    if (!SSH::isAvailable()) {
        respondError('SSH2 extension is not available', 500);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respondError('Invalid JSON input', 400);
        return;
    }

    try {
        $ssh = new SSH($input);
        $ssh->connect();
        $ssh->disconnect();
        echo json_encode(['success' => true, 'message' => 'SSH connection successful'], JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        respondError('SSH connection failed: ' . $e->getMessage(), 500);
    }
}

/** @throws JsonException */
function respondSSHListFiles(): void
{
    if (!SSH::isAvailable()) {
        respondError('SSH2 extension is not available', 500);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respondError('Invalid JSON input', 400);
        return;
    }

    $path = $input['path'] ?? '/var/log';
    if (empty($path)) {
        respondError('Path is required', 400);
        return;
    }

    try {
        $ssh = new SSH($input);
        $ssh->connect();
        $finder = new RemoteLogFinder($ssh);
        $files = $finder->findAll($path);
        $ssh->disconnect();
        echo json_encode(['success' => true, 'files' => $files], JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        respondError('SSH file listing failed: ' . $e->getMessage(), 500);
    }
}

/** @throws JsonException */
function respondSSHReadFile(): void
{
    if (!SSH::isAvailable()) {
        respondError('SSH2 extension is not available', 500);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respondError('Invalid JSON input', 400);
        return;
    }

    $path = $input['path'] ?? '';
    if (empty($path)) {
        respondError('Path is required', 400);
        return;
    }

    try {
        $ssh = new SSH($input);
        $ssh->connect();
        $content = $ssh->readFile($path);
        $ssh->disconnect();

        // Parse the content
        $parser = new LogParser();
        $entries = $parser->parseString($content);

        echo json_encode(['success' => true, 'entries' => $entries], JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        respondError('SSH file reading failed: ' . $e->getMessage(), 500);
    }
}

function respondError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
}
