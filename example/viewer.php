<?php
/**
 * fast-php-log-viewer — Composer entry point
 *
 * 1. composer require mafio69/fast-php-log-viewer
 * 2. Copy this file to your webroot
 * 3. Set LOG_DIR below to point at your logs directory
 * 4. Open in browser
 */

define('LOG_DIR', __DIR__ . '/logs');   // ← adjust this path

require_once __DIR__ . '/vendor/autoload.php';

if (isset($_GET['action'])) {
    require_once __DIR__ . '/vendor/mafio69/fast-php-log-viewer/src/api.php';
    exit;
}

require_once __DIR__ . '/vendor/mafio69/fast-php-log-viewer/index.php';
