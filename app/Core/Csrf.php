<?php

declare(strict_types=1);

namespace IasdCentral\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $posted = (string) ($_POST['_csrf'] ?? '');
        $session = (string) ($_SESSION['_csrf'] ?? '');

        if ($posted === '' || $session === '' || !hash_equals($session, $posted)) {
            http_response_code(419);
            exit('Sessao expirada. Volte e tente novamente.');
        }
    }
}

