<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\NewsRepository;

final class PublicController
{
    public function home(Request $request): void
    {
        $news = (new NewsRepository())->currentPublicItems();
        View::render('public/home', ['title' => 'IASD Central', 'news' => $news], 'layouts/public');
    }
}

