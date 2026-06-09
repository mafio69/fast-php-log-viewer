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
        'read-file' => respondReadFile(),
        'check-file' => respondCheckFile(),
        'demo-entries' => respondDemoEntries(),
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

/**
 * Read any local file directly (with existence check).
 * Auto-adds the parent directory to allowed dirs.
 */
function respondReadFile(): void
{
    $file = $_GET['file'] ?? '';
    if ($file === '') {
        respondError('Missing file parameter', 400);
        return;
    }

    // Normalize path (support Windows-style backslashes)
    $file = str_replace('\\', '/', $file);

    if (!file_exists($file)) {
        respondError('File not found: ' . $file, 404);
        return;
    }

    if (!is_readable($file)) {
        respondError('File not readable: ' . $file, 403);
        return;
    }

    // Auto-add directory to config so future requests work via normal entries endpoint
    $dir = dirname($file);
    try {
        $config = new LogConfig();
        $config->addDirectory([
            'name' => 'direct_' . md5($dir),
            'path' => $dir,
            'type' => 'local',
        ]);
    } catch (\Exception $e) {
        // Directory might already exist - that's fine
    }

    $parser = new LogParser();
    $entries = $parser->parseFile($file);

    echo json_encode($entries, JSON_THROW_ON_ERROR);
}

/**
 * Check if a local file exists and is readable.
 */
function respondCheckFile(): void
{
    $file = $_GET['file'] ?? '';
    if ($file === '') {
        respondError('Missing file parameter', 400);
        return;
    }

    $file = str_replace('\\', '/', $file);
    $exists = file_exists($file);
    $readable = $exists && is_readable($file);
    $size = $readable ? (filesize($file) ?: 0) : 0;

    echo json_encode([
        'exists' => $exists,
        'readable' => $readable,
        'size' => $size,
        'path' => $file,
    ], JSON_THROW_ON_ERROR);
}

/**
 * Return demo log entries in Symfony format for first-run fallback.
 */
function respondDemoEntries(): void
{
    $now = date('Y-m-d H:i:s');
    $entries = [
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'level' => 'INFO',
            'location' => 'src/Controller/HomeController.php:42',
            'message' => '[DEMO] request.INFO: Matched route "app_homepage".',
            'context' => ['route' => 'app_homepage', 'method' => 'GET', 'route_parameters' => ['_controller' => 'App\\Controller\\HomeController::index']],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-1 hour 55 minutes')),
            'level' => 'DEBUG',
            'location' => 'vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php:380',
            'message' => '[DEMO] doctrine.DEBUG: SELECT u0_.id, u0_.email FROM user u0_ WHERE u0_.id = ?',
            'context' => ['params' => [1], 'types' => ['integer']],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-1 hour 50 minutes')),
            'level' => 'WARNING',
            'location' => 'src/Service/PaymentService.php:128',
            'message' => '[DEMO] payment.WARNING: Payment gateway timeout, retrying...',
            'context' => ['gateway' => 'stripe', 'attempt' => 2, 'timeout_ms' => 5000],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'level' => 'ERROR',
            'location' => 'src/EventSubscriber/ExceptionSubscriber.php:55',
            'message' => '[DEMO] request.CRITICAL: Uncaught PHP Exception Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
            'context' => ['exception' => 'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException', 'message' => 'No route found for "GET /admin/secret"', 'code' => 404],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-1 hour 10 minutes')),
            'level' => 'NOTICE',
            'location' => 'src/Security/LoginAuthenticator.php:78',
            'message' => '[DEMO] security.NOTICE: User "admin@example.com" logged in successfully.',
            'context' => ['user' => 'admin@example.com', 'ip' => '192.168.1.100', 'firewall' => 'main'],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'level' => 'INFO',
            'location' => 'src/Command/CacheWarmupCommand.php:35',
            'message' => '[DEMO] cache.INFO: Cache warmup completed.',
            'context' => ['duration_ms' => 1250, 'items_cached' => 342],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
            'level' => 'ERROR',
            'location' => 'src/Repository/ProductRepository.php:92',
            'message' => '[DEMO] doctrine.ERROR: SQLSTATE[42S22]: Column not found: Unknown column "price_netto" in field list',
            'context' => ['query' => 'SELECT price_netto FROM product WHERE id = ?', 'params' => [15]],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'level' => 'CRITICAL',
            'location' => 'src/Kernel.php:65',
            'message' => '[DEMO] app.CRITICAL: Redis connection refused — fallback to file cache.',
            'context' => ['dsn' => 'redis://localhost:6379', 'error' => 'Connection refused'],
        ],
        [
            'datetime' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
            'level' => 'DEBUG',
            'location' => 'src/MessageHandler/SendEmailHandler.php:40',
            'message' => '[DEMO] messenger.DEBUG: Handling message App\\Message\\SendEmailNotification',
            'context' => ['transport' => 'async', 'recipient' => 'user@example.com'],
        ],
        [
            'datetime' => $now,
            'level' => 'INFO',
            'location' => '',
            'message' => '[DEMO] === To jest demonstracja — dodaj własny katalog logów lub połącz się przez SSH. ===',
            'context' => ['hint' => 'Użyj panelu po lewej stronie, aby dodać ścieżkę do logów lub nawiązać połączenie SSH.'],
        ],
    ];

    echo json_encode($entries, JSON_THROW_ON_ERROR);
}

function respondError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
}
