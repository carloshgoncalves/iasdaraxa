<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        if ((new Auth())->user()) {
            redirect('/dashboard');
        }

        View::render('auth/login', ['title' => 'Entrar'], 'layouts/guest');
    }

    public function login(Request $request): void
    {
        Csrf::verify();
        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        if (!(new Auth())->attempt($username, $password)) {
            $_SESSION['flash_error'] = 'Usuario ou senha invalidos.';
            redirect('/login');
        }

        redirect('/dashboard');
    }

    public function logout(): void
    {
        Csrf::verify();
        (new Auth())->logout();
        redirect('/');
    }
}
