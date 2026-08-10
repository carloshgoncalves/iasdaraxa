<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\MenuRepository;
use IasdCentral\Repositories\ObraRepository;

final class DashboardController
{
    public function index(Request $request): void
    {
        $user = Auth::requireLogin();
        $menus = (new MenuRepository())->activeTreeForUser((int) $user['id']);
        $obras = new ObraRepository();

        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'menus' => $menus,
            'obraSummary' => $obras->dashboardSummary(),
        ]);
    }
}

