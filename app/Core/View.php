<?php

declare(strict_types=1);

namespace IasdCentral\Core;

final class View
{
    private static string $basePath;

    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
    }

    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $content = self::capture($view, $data);
        echo self::capture($layout, array_merge($data, ['content' => $content]));
    }

    private static function capture(string $view, array $data): string
    {
        $file = self::$basePath . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View {$view} nao encontrada.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}

