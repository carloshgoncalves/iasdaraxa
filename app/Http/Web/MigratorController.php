<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\MenuRepository;

final class MigratorController
{
    public function index(Request $request): void
    {
        $user = (new Auth())->user();

        View::render('settings/migrator', [
            'title' => 'Publicacao FTP',
            'user' => $user,
            'menus' => $user ? (new MenuRepository())->activeTreeForUser((int) $user['id']) : [],
            'configExists' => is_file(APP_BASE_PATH . '/migrador/config.php'),
        ]);
    }

    public function updateFtp(Request $request): void
    {
        Csrf::verify();

        require APP_BASE_PATH . '/migrador/atualizarFtp.php';
    }
}
