<?php

declare(strict_types=1);

use IasdCentral\Core\Config;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) Config::get('app_url', ''), '/');
    $path = '/' . ltrim($path, '/');

    return $base . ($path === '/' ? '' : $path);
}

function asset(string $path): string
{
    $assetPath = rtrim((string) Config::get('asset_path', '/assets'), '/');
    return url($assetPath . '/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function date_br(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    return $time ? date('d/m/Y', $time) : $value;
}


function money_br(mixed $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}
