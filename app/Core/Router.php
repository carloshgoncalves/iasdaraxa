<?php

declare(strict_types=1);

namespace IasdCentral\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)}/', '(?P<$1>[0-9]+)', '/' . trim($path, '/'));
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as [$method, $pattern, $handler]) {
            if ($method !== $request->method() || !preg_match($pattern, $request->path(), $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $handler;
            (new $class())->$action($request, ...array_values($params));
            return;
        }

        http_response_code(404);
        View::render('public/not-found', ['title' => 'Pagina nao encontrada'], 'layouts/public');
    }
}

