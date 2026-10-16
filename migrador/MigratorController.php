<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Config;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;

final class MigratorController
{
    public function index(Request $request): void
    {
        $this->requireLocalDevelopment();
        $configExists = is_file(__DIR__ . '/config.php');

        require __DIR__ . '/view.php';
    }

    public function updateFtp(Request $request): void
    {
        $this->requireLocalDevelopment();
        Csrf::verify();

        require __DIR__ . '/atualizarFtp.php';
    }

    public function syncDatabase(Request $request): void
    {
        $this->requireLocalDevelopment();
        Csrf::verify();

        require __DIR__ . '/sincronizarBancoProducao.php';
    }

    private function requireLocalDevelopment(): void
    {
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $isLoopback = in_array($remoteAddress, ['127.0.0.1', '::1'], true);

        if (Config::get('app_env', 'production') !== 'development' || !$isLoopback) {
            http_response_code(404);
            exit('Pagina nao encontrada.');
        }
    }
}
