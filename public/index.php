<?php

declare(strict_types=1);

$privatePathCandidates = array_filter([
    getenv('APP_PRIVATE_PATH') ?: null,
    dirname(__DIR__),
    dirname(__DIR__) . '/appiasdaraxa',
    dirname(dirname(__DIR__)) . '/appiasdaraxa',
    '/appiasdaraxa',
]);

$privatePath = null;
foreach ($privatePathCandidates as $candidate) {
    $candidate = rtrim((string) $candidate, '/');
    if (is_file($candidate . '/bootstrap.php')) {
        $privatePath = $candidate;
        break;
    }
}

if ($privatePath === null) {
    http_response_code(500);
    exit('Pasta privada do iasdaraxa nao encontrada.');
}

$app = require $privatePath . '/bootstrap.php';
$router = $app['router'];

require APP_BASE_PATH . '/routes/web.php';

$router->dispatch(new IasdCentral\Core\Request());
