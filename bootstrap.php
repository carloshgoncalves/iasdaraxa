<?php

declare(strict_types=1);

use IasdCentral\Core\Auth;
use IasdCentral\Core\Config;
use IasdCentral\Core\Database;
use IasdCentral\Core\Router;
use IasdCentral\Core\View;

define('APP_BASE_PATH', __DIR__);

spl_autoload_register(function (string $class): void {
    $prefix = 'IasdCentral\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = APP_BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once APP_BASE_PATH . '/app/Support/helpers.php';

$config = require APP_BASE_PATH . '/config/app.php';
$localEnv = APP_BASE_PATH . '/env.php';
if (is_file($localEnv)) {
    $config = array_replace_recursive($config, require $localEnv);
}

Config::load($config);
date_default_timezone_set((string) Config::get('timezone', 'America/Bahia'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) Config::get('session.name', 'iasdaraxa_session'));
    session_start();
}

Database::configure((array) Config::get('database', []));
View::setBasePath(APP_BASE_PATH . '/resources/views');

return [
    'router' => new Router(),
    'auth' => new Auth(),
];

