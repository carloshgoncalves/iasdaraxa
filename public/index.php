<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap.php';
$router = $app['router'];

require APP_BASE_PATH . '/routes/web.php';

$router->dispatch(new IasdCentral\Core\Request());

