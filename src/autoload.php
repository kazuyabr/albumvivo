<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 próprio (~30 linhas) — o projeto não usa Composer.
 *
 *   Freebuff\Core\Config   ->  src/Core/Config.php
 *   Freebuff\Ai\Replicate  ->  src/Ai/Replicate.php
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Freebuff\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/helpers.php';
