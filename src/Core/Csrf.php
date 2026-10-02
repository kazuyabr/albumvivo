<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Proteção CSRF por token aleatório gravado na sessão.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        $key = (string) Config::get('security.csrf_key', '_csrf');

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            e($key),
            e(self::token())
        );
    }

    public static function key(): string
    {
        return (string) Config::get('security.csrf_key', '_csrf');
    }

    public static function validate(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $expected = Session::get(self::SESSION_KEY);

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * Valida a requisição; lança HttpException(400) quando inválida.
     */
    public static function assertValid(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $request->input(self::key()) ?? $request->header('x-csrf-token');

        if (!self::validate(is_string($token) ? $token : null)) {
            throw HttpException::csrf();
        }
    }
}
