<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Renderização de templates PHP com layout opcional.
 *
 *   View::render('home', $data, 'layouts/main');
 *   View::partial('errors/404', $data);
 */
final class View
{
    private static ?string $viewsDir = null;

    private static string $defaultLayout = 'layouts/main';

    public static function viewsDir(): string
    {
        return self::$viewsDir ??= dirname(__DIR__, 2) . '/views';
    }

    public static function setViewsDir(string $dir): void
    {
        self::$viewsDir = rtrim($dir, '/');
    }

    public static function defaultLayout(string $layout): void
    {
        self::$defaultLayout = $layout;
    }

    /**
     * Renderiza um template; se $layout for informado, envolve o conteúdo.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = '__default__'): string
    {
        $content = self::partial($template, $data);

        if ($layout === null) {
            return $content;
        }

        if ($layout === '__default__') {
            $layout = self::$defaultLayout;
        }

        if ($layout === '' || $layout === false) {
            return $content;
        }

        return self::partial($layout, $data + ['content' => $content]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function partial(string $template, array $data = []): string
    {
        $file = self::resolve($template);

        if ($file === null) {
            throw new \RuntimeException('Template não encontrado: ' . $template);
        }

        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();

            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            return (string) ob_get_clean();
        })($file, $data);
    }

    public static function exists(string $template): bool
    {
        return self::resolve($template) !== null;
    }

    private static function resolve(string $template): ?string
    {
        if (preg_match('#^(?:\.\.[/\\\\])|^[/\\\\]|\\\\#', $template) === 1) {
            return null;
        }

        $file = self::viewsDir() . '/' . trim(str_replace('\\', '/', $template), '/') . '.php';

        return is_file($file) ? $file : null;
    }
}
