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

// Enable error logging - only to file, not display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../data/php_errors.log');
error_reporting(E_ALL);

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: dirname(__DIR__) . '/logs');
}

if (!defined('LOG_DIRS')) {
    define('LOG_DIRS', []);
}

$_autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}
unset($_autoload);

use Mariusz\LogViewer\Service\LogFinder;
use Mariusz\LogViewer\Service\LogParser;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\LogScanner;
use Mariusz\LogViewer\Service\SSH;
use Mariusz\LogViewer\Service\RemoteLogFinder;

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
        'config-cleanup-duplicates' => respondCleanupDuplicates(),
        'config-update-dir' => respondUpdateDirectory(),
        'config-delete-dir' => respondDeleteDirectory(),
        'config-init-defaults' => respondInitDefaults(),
        'scan-directories' => respondScanDirectories(),
        'ssh-test-connection' => respondTestSSHConnection(),
        'ssh-list-files' => respondSSHListFiles(),
        'ssh-read-file' => respondSSHReadFile(),
        'ssh-download-file' => respondSSHDownloadFile(),
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

    // Also add directories from LogConfig
    try {
        $config = new LogConfig();
        $configDirs = $config->getDirectories();
        foreach ($configDirs as $dir) {
            $dirs[$dir['name']] = $dir['path']; // Use 'name' as key
        }
    } catch (Exception $e) {
        // LogConfig might not be initialized yet - log error but continue
        error_log('LogConfig error: ' . $e->getMessage());
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
    try {
        $config = new LogConfig();
        $dirs = $config->getDirectories();

        // Convert to consistent format with 'key' instead of 'name'
        $dirs = array_map(fn($d) => ['key' => $d['name'], 'path' => $d['path']], $dirs);

        // Also include LOG_DIR/LOG_DIRS for backwards compatibility
        $envDirs = getLogDirs();
        foreach ($envDirs as $key => $path) {
            // Avoid duplicates
            if (!in_array($key, array_column($dirs, 'key'))) {
                $dirs[] = ['key' => $key, 'path' => $path];
            }
        }

        echo json_encode($dirs, JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        // Fallback to environment dirs if LogConfig fails
        $dirs = [];
        $envDirs = getLogDirs();
        foreach ($envDirs as $key => $path) {
            $dirs[] = ['key' => $key, 'path' => $path];
        }
        echo json_encode($dirs, JSON_THROW_ON_ERROR);
    }
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
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            respondError('Invalid JSON input', 400);
            return;
        }

        $config = new LogConfig();
        $id = $config->addDirectory($input);
        echo json_encode(['success' => true, 'id' => $id], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        respondError('Failed to add directory: ' . $e->getMessage(), 500);
    }
}

/** @throws JsonException */
function respondCleanupDuplicates(): void
{
    try {
        $config = new LogConfig();
        $removed = $config->removeDuplicates();
        echo json_encode(['success' => true, 'removed' => $removed], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        respondError('Failed to cleanup duplicates: ' . $e->getMessage(), 500);
    }
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
        $allFiles = $input['allFiles'] ?? false;
        $files = $finder->findAll($path, $allFiles);
        $ssh->disconnect();
        echo json_encode(['success' => true, 'files' => $files], JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        error_log('SSH file listing failed: ' . $e->getMessage());
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
        error_log('SSH file reading failed: ' . $e->getMessage());
        respondError('SSH file reading failed: ' . $e->getMessage(), 500);
    }
}

function respondError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
}

/** @throws JsonException */
function respondSSHDownloadFile(): void
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

    $remotePath = $input['remotePath'] ?? '';
    $localName = $input['localName'] ?? basename($remotePath);

    if (empty($remotePath)) {
        respondError('Remote path is required', 400);
        return;
    }

    try {
        $ssh = new SSH($input);
        $ssh->connect();
        $content = $ssh->readFile($remotePath);
        $ssh->disconnect();

        if (empty($content)) {
            respondError('Remote file is empty or could not be read', 400);
            return;
        }

        // Save to temp directory with original name
        $tempDir = __DIR__ . '/../../temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $localPath = $tempDir . '/' . $localName;
        if (file_put_contents($localPath, $content) === false) {
            respondError('Failed to save file locally', 500);
            return;
        }

        echo json_encode(['success' => true, 'localPath' => $localPath, 'size' => strlen($content)], JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        respondError('SSH file download failed: ' . $e->getMessage(), 500);
    }
}
