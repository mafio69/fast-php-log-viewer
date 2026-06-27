<?php
/**
 * fast-php-log-viewer — Composer entry point
 *
 * Setup:
 *   1. composer require mafio69/log-viewer
 *   2. Copy the contents of vendor/mafio69/log-viewer/public/ to your webroot:
 *        cp -r vendor/mafio69/log-viewer/public/css  <webroot>/
 *        cp -r vendor/mafio69/log-viewer/public/js   <webroot>/
 *   3. Copy this file to your webroot
 *   4. Adjust LOG_DIR to your logs directory
 *   5. Open in browser
 *
 * Optional:
 *   define('EDITOR_URL', 'phpstorm://open?file={file}&line={line}');
 *   define('DATA_DIR',  __DIR__ . '/data');   // persistent data location
 *   define('ROOT_DIR',  __DIR__);              // project root
 */

define('LOG_DIR', __DIR__ . '/logs');

// Override these before including if needed:
// define('ROOT_DIR',  __DIR__);
// define('DATA_DIR',  __DIR__ . '/data');
// define('EDITOR_URL', 'phpstorm://open?file={file}&line={line}');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/mafio69/log-viewer/src/Bootstrap/frontend.php';
