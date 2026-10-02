<?php

declare(strict_types=1);

use Freebuff\Core\Config;

if (!function_exists('e')) {
    /**
     * Escapa valor para HTML (padrão em todos os templates).
     */
    function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cfg')) {
    /**
     * Acesso curto à configuração: cfg('plans.sub_monthly.price_cents').
     */
    function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}
