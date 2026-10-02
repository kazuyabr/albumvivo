<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Configuração carregada uma única vez de config/app.php.
 * Suporta acesso por ponto: Config::get('plans.sub_monthly.price_cents').
 */
final class Config
{
    /** @var array<string,mixed>|null */
    private static ?array $items = null;

    private static bool $booted = false;

    /** @var array<string,mixed> sobrescritas em tempo de execução (admin) */
    private static array $overrides = [];

    public static function boot(?string $baseDir = null): void
    {
        if (self::$booted) {
            return;
        }

        $base = $baseDir ?? dirname(__DIR__, 2);

        Env::loadDotEnv($base . '/.env');

        /** @var array<string,mixed> $items */
        $items = require $base . '/config/app.php';

        self::$items = $items;
        self::$booted = true;

        date_default_timezone_set((string) ($items['app']['timezone'] ?? 'UTC'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $found = false;
        $value = self::resolve($key, $found);

        return $found ? $value : $default;
    }

    public static function has(string $key): bool
    {
        $found = false;
        self::resolve($key, $found);

        return $found;
    }

    /**
     * Sobrescreve um valor em tempo de execução (ex.: plano editado no admin).
     * Sobrescritas têm prioridade sobre config/app.php.
     */
    public static function override(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        self::boot();

        $items = self::$items ?? [];

        foreach (self::$overrides as $key => $value) {
            self::assign($items, $key, $value);
        }

        return $items;
    }

    public static function reset(): void
    {
        self::$items = null;
        self::$booted = false;
        self::$overrides = [];
    }

    private static function resolve(string $key, bool &$found): mixed
    {
        self::boot();

        if (array_key_exists($key, self::$overrides)) {
            $found = true;

            return self::$overrides[$key];
        }

        $node = self::$items ?? [];

        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                $found = false;

                return null;
            }

            $node = $node[$part];
        }

        $found = true;

        return $node;
    }

    private static function assign(array &$items, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $node = &$items;
        $last = count($parts) - 1;

        foreach ($parts as $i => $part) {
            if ($i === $last) {
                $node[$part] = $value;

                return;
            }

            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }

            $node = &$node[$part];
        }
    }
}
