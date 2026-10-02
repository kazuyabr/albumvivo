<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Leitura de variáveis de ambiente + parser de .env sem dependências.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $loaded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            $value = self::$loaded[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return self::cast((string) $value);
    }

    public static function set(string $key, string $value): void
    {
        self::$loaded[$key] = $value;
        putenv($key . '=' . $value);
    }

    public static function isTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function cast(string $value): mixed
    {
        $lower = strtolower($value);

        return match (true) {
            $lower === 'true', $lower === '(true)' => true,
            $lower === 'false', $lower === '(false)' => false,
            $lower === 'null', $lower === '(null)' => null,
            $value !== '' && ctype_digit(ltrim($value, '-')) => (int) $value,
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }

    /**
     * Carrega um arquivo .env no ambiente, sem sobrescrever variáveis já
     * definidas (Docker/compose tem prioridade).
     */
    public static function loadDotEnv(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }

            [$key, $raw] = [$m[1], trim($m[2])];

            if (getenv($key) !== false || isset($_ENV[$key], self::$loaded[$key])) {
                continue;
            }

            if ((str_starts_with($raw, '"') && str_ends_with($raw, '"'))
                || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
                $raw = substr($raw, 1, -1);
            }

            $envLine = $key . '=' . $raw;
            putenv($envLine);
            $_ENV[$key] = $raw;
            self::$loaded[$key] = $raw;
        }
    }
}
