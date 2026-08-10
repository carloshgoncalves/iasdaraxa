<?php

declare(strict_types=1);

namespace IasdCentral\Core;

final class Request
{
    public function method(): string
    {
        $method = strtoupper((string) ($_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'GET';
    }

    public function path(): string
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        if ($scriptName !== '' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName)) ?: '/';
        } else {
            $script = dirname($scriptName);
            if ($script !== '/' && $script !== '\\' && str_starts_with($uri, $script)) {
                $uri = substr($uri, strlen($script)) ?: '/';
            }
        }

        return '/' . trim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }
}

