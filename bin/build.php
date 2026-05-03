<?php

/**
 * Builds dist/fast-php-log-viewer.php — a single self-contained file.
 * Usage: php bin/build.php
 */

$root   = dirname(__DIR__);
$dist   = $root . '/dist/fast-php-log-viewer.php';
$src    = $root . '/src';
$index  = $root . '/index.php';

if (!is_dir(dirname($dist))) {
    mkdir(dirname($dist), 0755, true);
}

// Extract class body (strip <?php, declare, namespace, use statements)
function extractClass(string $path): string
{
    $code = file_get_contents($path);
    // Remove opening tag, declare, namespace, use lines
    $code = preg_replace('/^<\?php\s*/m', '', $code);
    $code = preg_replace('/^declare\s*\([^)]+\);\s*/m', '', $code);
    $code = preg_replace('/^namespace\s+[^;]+;\s*/m', '', $code);
    $code = preg_replace('/^use\s+[^;]+;\s*/m', '', $code);
    return trim($code) . "\n";
}

// Extract index.php body — everything after the api.php require
function extractIndex(string $path): string
{
    $code = file_get_contents($path);
    // Remove the PHP block at the top (config + api require + exit)
    $code = preg_replace('/<\?php.*?\?>/s', '', $code, 1);
    return trim($code) . "\n";
}

$logParser  = extractClass($src . '/LogParser.php');
$logFinder  = extractClass($src . '/LogFinder.php');
$apiCode    = file_get_contents($src . '/api.php');

// Strip the require_once autoload line from api.php — classes will be inlined
$apiCode = preg_replace('/^<\?php\s*/m', '', $apiCode);
$apiCode = preg_replace('/^declare\s*\([^)]+\);\s*/m', '', $apiCode);
$apiCode = preg_replace('/^use\s+[^;]+;\s*/m', '', $apiCode);
$apiCode = preg_replace('/^require_once[^;]+;\s*/m', '', $apiCode);
$apiCode = trim($apiCode);

$html = extractIndex($index);

$output = <<<PHP
<?php
/**
 * fast-php-log-viewer — single-file drop-in
 * https://github.com/mafio69/fast-php-log-viewer
 *
 * Usage (no Composer needed):
 *   1. Copy this file to your project
 *   2. Set LOG_DIR to your logs directory
 *   3. Open in browser
 *
 * @version 1.0.0
 */

declare(strict_types=1);

if (!defined('LOG_DIR')) {
    define('LOG_DIR', getenv('LOG_DIR') ?: __DIR__ . '/logs');
}

namespace Mariusz\\LogViewer {

$logParser
$logFinder
}

namespace {

use Mariusz\\LogViewer\\LogFinder;
use Mariusz\\LogViewer\\LogParser;

if (isset(\$_GET['action'])) {
$apiCode
    exit;
}

?>
$html
PHP;

file_put_contents($dist, $output);
echo "Built: $dist\n";
echo "Size:  " . number_format(filesize($dist)) . " bytes\n";
