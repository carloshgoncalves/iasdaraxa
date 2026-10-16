<?php

declare(strict_types=1);

use IasdCentral\Http\Web\MigratorController;

require_once __DIR__ . '/MigratorController.php';

$router->get('/migrador', [MigratorController::class, 'index']);
$router->post('/migrador/atualizar-ftp', [MigratorController::class, 'updateFtp']);
$router->post('/migrador/sincronizar-banco', [MigratorController::class, 'syncDatabase']);
