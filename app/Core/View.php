<?php

namespace App\Core;

/**
 * Simple View Renderer for PHP Native
 */
class View
{
    /**
     * Render a view file inside app/Views/
     */
    public static function render(string $viewPath, array $data = []): void
    {
        extract($data);

        $file = dirname(__DIR__) . '/Views/' . ltrim($viewPath, '/') . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("View file not found: {$file}");
        }

        require $file;
    }

    /**
     * Render layout header
     */
    public static function header(array $data = []): void
    {
        extract($data);
        require dirname(__DIR__) . '/Views/layouts/header.php';
    }

    /**
     * Render layout sidebar
     */
    public static function sidebar(array $data = []): void
    {
        extract($data);
        require dirname(__DIR__) . '/Views/layouts/sidebar.php';
    }
}

