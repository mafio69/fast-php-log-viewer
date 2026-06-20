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
        'config-cleanup-allowed' => respondCleanupAllowed(),
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

    // Also add directories from JSON config file (fallback for systems without SQLite)
    $jsonConfigFile = dirname(__DIR__, 2).'/data/directories.json';
    if (file_exists($jsonConfigFile)) {
        $jsonConfig = json_decode(file_get_contents($jsonConfigFile), true);
        if (is_array($jsonConfig)) {
            foreach ($jsonConfig as $dir) {
                $dirs[$dir['name']] = $dir['path'];
            }
        }
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

        // Try to add to SQLite first
        try {
            $config = new LogConfig();
            $id = $config->addDirectory($input);
            echo json_encode(['success' => true, 'id' => $id], JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            // Fallback to JSON config if SQLite fails
            error_log('SQLite addDirectory failed, using JSON fallback: '.$e->getMessage());
            $jsonConfigFile = dirname(__DIR__, 2).'/data/directories.json';
            $directories = [];
            if (file_exists($jsonConfigFile)) {
                $directories = json_decode(file_get_contents($jsonConfigFile), true) ?: [];
            }

            // Check for duplicates
            foreach ($directories as $dir) {
                if ($dir['path'] === $input['path']) {
                    respondError('Directory already exists: '.$input['path'], 400);

                    return;
                }
            }

            $directories[] = [
                'name' => $input['name'],
                'path' => $input['path'],
                'type' => $input['type'] ?? 'local',
            ];

            if (file_put_contents($jsonConfigFile, json_encode($directories, JSON_PRETTY_PRINT))) {
                echo json_encode(['success' => true, 'id' => count($directories) - 1], JSON_THROW_ON_ERROR);
            } else {
                respondError('Failed to save directory configuration', 500);
            }
        }
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
function respondCleanupAllowed(): void
{
    try {
        $config = new LogConfig();
        $removed = $config->removeAllowedEntries();
        echo json_encode(['success' => true, 'removed' => $removed], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        respondError('Failed to cleanup allowed entries: '.$e->getMessage(), 500);
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

    // Try SQLite first
    try {
        $config = new LogConfig();
        $success = $config->deleteDirectory($id);
        echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        // Fallback to JSON config
        error_log('SQLite deleteDirectory failed, using JSON fallback: '.$e->getMessage());
        $jsonConfigFile = dirname(__DIR__, 2).'/data/directories.json';
        $directories = [];
        if (file_exists($jsonConfigFile)) {
            $directories = json_decode(file_get_contents($jsonConfigFile), true) ?: [];
        }

        if (isset($directories[$id])) {
            unset($directories[$id]);
            $directories = array_values($directories); // Reindex array
            $success = file_put_contents($jsonConfigFile, json_encode($directories, JSON_PRETTY_PRINT)) !== false;
            echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
        } else {
            respondError('Directory not found', 404);
        }
    }
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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

        // Security: validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if (strlen($content) > $maxSize) {
            respondError('File too large (max 10MB)', 400);
            return;
        }

        // Security: sanitize filename
        $localName = sanitizeFilename($localName);
        if (empty($localName)) {
            $localName = 'downloaded_file.log';
        }

        // Security: validate file type (check for binary/executable content)
        if (isBinaryContent($content)) {
            respondError('Binary or executable files are not allowed', 400);
            return;
        }

        // Security: validate file is text-based (log files)
        if (!isValidTextFile($content)) {
            respondError('File does not appear to be a valid text/log file', 400);
            return;
        }

        // Security: check for suspicious patterns (PHP tags, shell commands, etc.)
        if (containsSuspiciousContent($content)) {
            respondError('File contains potentially dangerous content', 400);
            return;
        }

        // Create temp directory if it doesn't exist
        $tempDir = __DIR__ . '/../../temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Security: generate unique temp filename first
        $tempName = uniqid('temp_', true) . '.tmp';
        $tempPath = $tempDir . '/' . $tempName;

        // Save to temp file first
        if (file_put_contents($tempPath, $content) === false) {
            respondError('Failed to save file to temp directory', 500);
            return;
        }

        // Security: validate the temp file
        $realTempPath = realpath($tempPath);
        $realTempDir = realpath($tempDir);
        if ($realTempPath === false || strpos($realTempPath, $realTempDir) !== 0) {
            unlink($tempPath);
            respondError('Invalid temp file path', 403);
            return;
        }

        // Move to final destination with sanitized name
        $finalPath = $tempDir . '/' . $localName;
        if (!rename($tempPath, $finalPath)) {
            // If rename fails, try copy and delete
            if (!copy($tempPath, $finalPath)) {
                unlink($tempPath);
                respondError('Failed to move file to final destination', 500);
                return;
            }
            unlink($tempPath);
        }

        // Security: validate final path
        $realFinalPath = realpath($finalPath);
        if ($realFinalPath === false || strpos($realFinalPath, $realTempDir) !== 0) {
            unlink($finalPath);
            respondError('Invalid final file path', 403);
            return;
        }

        echo json_encode(['success' => true, 'localPath' => $finalPath, 'size' => strlen($content)], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        respondError('SSH file download failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Sanitize filename to prevent directory traversal and other attacks
 */
function sanitizeFilename(string $filename): string
{
    // Remove directory traversal attempts
    $filename = basename($filename);

    // Remove null bytes
    $filename = str_replace("\0", '', $filename);

    // Remove dangerous characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Limit length
    $filename = substr($filename, 0, 255);

    // Ensure it's not empty
    return empty($filename) ? 'file.log' : $filename;
}

/**
 * Check if content appears to be binary
 */
function isBinaryContent(string $content): bool
{
    // Check for null bytes (common in binary files)
    if (strpos($content, "\0") !== false) {
        return true;
    }

    // Check binary character ratio
    $length = strlen($content);
    if ($length === 0) {
        return false;
    }

    $binaryCount = 0;
    $sampleSize = min($length, 1000); // Check first 1000 bytes

    for ($i = 0; $i < $sampleSize; $i++) {
        $char = ord($content[$i]);
        // Binary characters are typically outside printable ASCII range
        if ($char < 9 || ($char > 13 && $char < 32) || $char > 126) {
            $binaryCount++;
        }
    }

    // If more than 30% binary characters, consider it binary
    return ($binaryCount / $sampleSize) > 0.3;
}

/**
 * Check if content is valid text/log file
 */
function isValidTextFile(string $content): bool
{
    // Check for common text file patterns
    $textPatterns = [
        '/\d{4}-\d{2}-\d{2}/', // Date format
        '/\d{2}:\d{2}:\d{2}/', // Time format
        '/\[(ERROR|WARNING|INFO|DEBUG|CRITICAL)\]/i', // Log levels
        '/\w+:\d+/', // File:line format
    ];

    $matches = 0;
    foreach ($textPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $matches++;
        }
    }

    // Require at least one text pattern or be mostly printable ASCII
    if ($matches > 0) {
        return true;
    }

    // Fallback: check if mostly printable ASCII
    $printableCount = 0;
    $sampleSize = min(strlen($content), 1000);
    for ($i = 0; $i < $sampleSize; $i++) {
        $char = ord($content[$i]);
        if (($char >= 32 && $char <= 126) || $char === 10 || $char === 13 || $char === 9) {
            $printableCount++;
        }
    }

    return ($printableCount / $sampleSize) > 0.8;
}

/**
 * Check for suspicious content (PHP tags, shell commands, etc.)
 */
function containsSuspiciousContent(string $content): bool
{
    $suspiciousPatterns = [
        '/<\?php/i',           // PHP opening tag
        '/<\?=/i',             // PHP short echo tag
        '/<script/i',          // HTML script tag
        '/#!/usr\/bin\//',     // Shebang
        '/eval\(/i',           // PHP eval function
        '/exec\(/i',           // PHP exec function
        '/system\(/i',         // PHP system function
        '/passthru\(/i',       // PHP passthru function
        '/shell_exec\(/i',     // PHP shell_exec function
        '/`.*`/',              // Backtick execution
        '/\$_GET/i',           // PHP $_GET
        '/\$_POST/i',          // PHP $_POST
        '/\$_REQUEST/i',       // PHP $_REQUEST
        '/\$_COOKIE/i',        // PHP $_COOKIE
        '/file_get_contents\(/i', // PHP file_get_contents
        '/file_put_contents\(/i', // PHP file_put_contents
        '/fopen\(/i',          // PHP fopen
        '/fwrite\(/i',         // PHP fwrite
        '/include\(/i',        // PHP include
        '/require\(/i',        // PHP require
        '/curl_exec\(/i',      // PHP curl_exec
        '/base64_decode\(/i',  // PHP base64_decode
    ];

    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    return false;
}
