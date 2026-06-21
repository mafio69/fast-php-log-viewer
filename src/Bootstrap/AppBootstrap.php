<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Bootstrap;

use Slim\App;

class AppBootstrap
{
    public static function create(): App
    {
        $appFactory = require __DIR__ . '/app.php';
        return $appFactory();
    }
}