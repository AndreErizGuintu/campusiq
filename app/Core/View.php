<?php

namespace App\Core;

/**
 * Renders PHP views from app/Views: pages inside layouts, plus partials.
 */
class View
{
    private const ROOT = BASE_PATH . '/app/Views/';

    /** Render pages/{page}.php, then wrap it in layouts/{layout}.php (null = no layout). */
    public static function render(string $page, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::capture(self::ROOT . 'pages/' . $page . '.php', $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture(self::ROOT . 'layouts/' . $layout . '.php', $data + ['content' => $content]);
    }

    public static function partial(string $name, array $data = []): string
    {
        return self::capture(self::ROOT . 'partials/' . $name . '.php', $data);
    }

    private static function capture(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException('View not found: ' . str_replace(BASE_PATH, '', $__file));
        }

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
