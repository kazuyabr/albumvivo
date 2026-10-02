<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Logger em arquivo, uma linha por evento (JSON), sem dependências.
 * Arquivo: storage/logs/app-YYYY-MM-DD.log
 */
final class Log
{
    private const LEVELS = [
        'debug' => 10,
        'info' => 20,
        'notice' => 25,
        'warning' => 30,
        'error' => 40,
        'critical' => 50,
    ];

    private static bool $installed = false;

    public static function install(): void
    {
        if (self::$installed) {
            return;
        }

        self::$installed = true;

        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('app.debug') ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::dir() . '/php-error.log');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            self::error($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);

            return true;
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write('notice', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $threshold = strtolower((string) Config::get('log.level', 'debug'));
        $thresholdValue = self::LEVELS[$threshold] ?? 10;

        if ((self::LEVELS[$level] ?? 0) < $thresholdValue) {
            return;
        }

        $dir = self::dir();

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $entry = json_encode(
            [
                'ts' => date('c'),
                'level' => $level,
                'msg' => $message,
                'ctx' => $context,
                'req' => self::requestId(),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($entry === false) {
            return;
        }

        @file_put_contents(
            $dir . '/app-' . date('Y-m-d') . '.log',
            $entry . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function dir(): string
    {
        return (string) Config::get('log.path', dirname(__DIR__, 2) . '/storage/logs');
    }

    private static function requestId(): string
    {
        static $id = null;

        if ($id === null) {
            $id = substr(bin2hex(random_bytes(8)), 0, 12);
        }

        return $id;
    }
}
