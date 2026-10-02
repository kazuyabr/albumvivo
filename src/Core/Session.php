<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Sessão persistida na tabela `sessions` (token em cookie, hash no banco).
 * Sem handler nativo do PHP: o token nunca é gravado em texto puro.
 */
final class Session
{
    private const COOKIE = 'freebuff_sid';

    private static bool $started = false;

    private static ?array $record = null;

    private static string $token = '';

    /** @var array<string,mixed> alterações pendentes de gravação */
    private static array $dirty = [];

    private static bool $regenerate = false;

    /** Banco indisponível: a sessão degrada para memória e a página continua. */
    private static bool $degraded = false;

    /** Garante que a sessão seja encerrada ao fim da requisição. */
    public static function boot(): void
    {
        if (self::$started) {
            return;
        }

        self::start();
    }

    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        self::$started = true;

        try {
            self::boot_();
        } catch (\Throwable $e) {
            Log::warning('Sessão degradada (banco indisponível): ' . $e->getMessage());
            self::$degraded = true;
            self::$record = ['id' => null, 'token_hash' => '', 'user_id' => null, 'data' => []];
        }
    }

    private static function boot_(): void
    {
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        $record = null;

        if (is_string($cookie) && $cookie !== '') {
            $record = self::find($cookie);
        }

        if ($record === null) {
            $record = self::create($cookie === '' ? self::generateToken() : $cookie);
            self::sendCookie($record['token']);
        }

        self::$record = $record;
        self::touch($record);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::boot();

        $data = self::$record['data'] ?? [];
        $data = is_array($data) ? $data : ((array) json_decode((string) $data, true) ?: []);

        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::boot();
        self::$dirty[$key] = $value;
        self::flush();
    }

    public static function forget(string $key): void
    {
        self::boot();
        unset(self::$dirty[$key]);
        self::remove($key);
    }

    public static function all(): array
    {
        self::boot();

        $data = self::$record['data'] ?? [];

        return is_array($data) ? $data : ((array) json_decode((string) $data, true) ?: []);
    }

    /**
     * Mensagem flash: gravada agora, consumida uma única vez.
     */
    public static function flash(string $key, mixed $value): void
    {
        self::set('_flash.' . $key, $value);
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        self::boot();

        $value = self::get('_flash.' . $key, $default);
        self::forget('_flash.' . $key);

        return $value;
    }

    public static function userId(): ?int
    {
        $id = self::get('user_id');

        return $id === null ? null : (int) $id;
    }

    public static function login(int $userId): void
    {
        self::set('user_id', $userId);
        self::regenerate();
    }

    public static function logout(): void
    {
        self::boot();

        if (self::$record !== null && self::$record['token_hash'] !== '') {
            self::destroyRow((string) self::$record['token_hash']);
        }

        // Começa uma sessão limpa no mesmo request (login em seguida funciona).
        unset($_COOKIE[self::COOKIE]);
        self::$record = null;
        self::$dirty = [];
        self::$token = '';
        self::$started = false;

        if (headers_sent() === false) {
            setcookie(self::COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        self::start();
    }

    public static function regenerate(): void
    {
        self::$regenerate = true;
    }

    public static function id(): ?string
    {
        self::boot();

        return self::$record['id'] ?? null;
    }

    public static function token(): string
    {
        self::boot();

        return self::$token;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed>|null */
    private static function find(string $token): ?array
    {
        try {
            $hash = hash('sha256', $token);

            $row = Database::fetch(
                'SELECT * FROM sessions WHERE token_hash = ? AND expires_at > ? LIMIT 1',
                [$hash, date('Y-m-d H:i:s')]
            );

            if ($row !== null) {
                self::$token = $token;
            }

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private static function create(string $token): array
    {
        $hash = hash('sha256', $token);
        $lifetime = (int) Config::get('security.session_lifetime_days', 30);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $lifetime * 86400);

        Database::insert('sessions', [
            'token_hash' => $hash,
            'user_id' => null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'data' => '{}',
            'last_seen_at' => $now,
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        self::$token = $token;

        return [
            'id' => null,
            'token' => $token,
            'token_hash' => $hash,
            'user_id' => null,
            'data' => [],
            'created_at' => $now,
        ];
    }

    /** @param array<string,mixed> $record */
    private static function touch(array $record): void
    {
        if (self::$degraded) {
            return;
        }

        try {
            Database::execute(
                'UPDATE sessions SET last_seen_at = ? WHERE token_hash = ?',
                [date('Y-m-d H:i:s'), $record['token_hash']]
            );
        } catch (\Throwable) {
            // Sessão é best-effort: nunca derruba a requisição.
        }
    }

    private static function flush(): void
    {
        if (self::$degraded || self::$record === null || self::$dirty === []) {
            return;
        }

        $data = self::all();

        foreach (self::$dirty as $key => $value) {
            $data[$key] = $value;
        }

        self::$dirty = [];

        try {
            Database::execute(
                'UPDATE sessions SET data = ?, user_id = ? WHERE token_hash = ?',
                [
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    $data['user_id'] ?? null,
                    self::$record['token_hash'],
                ]
            );

            self::$record['data'] = $data;

            if (self::$regenerate) {
                self::$regenerate = false;
                self::rotate();
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    private static function remove(string $key): void
    {
        if (self::$degraded) {
            return;
        }

        $data = self::all();
        unset($data[$key]);

        try {
            Database::execute(
                'UPDATE sessions SET data = ? WHERE token_hash = ?',
                [
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    self::$record['token_hash'],
                ]
            );

            self::$record['data'] = $data;
        } catch (\Throwable) {
            // best-effort
        }
    }

    private static function rotate(): void
    {
        if (self::$degraded) {
            return;
        }

        $new = self::generateToken();
        $newHash = hash('sha256', $new);

        try {
            Database::execute(
                'UPDATE sessions SET token_hash = ? WHERE token_hash = ?',
                [$newHash, self::$record['token_hash']]
            );

            self::$record['token_hash'] = $newHash;
            self::$token = $new;
            self::sendCookie($new);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private static function destroyRow(string $hash): void
    {
        try {
            Database::execute('DELETE FROM sessions WHERE token_hash = ?', [$hash]);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function sendCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        $days = (int) Config::get('security.session_lifetime_days', 30);

        setcookie(self::COOKIE, $token, [
            'expires' => time() + $days * 86400,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? 'off') !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /** @internal usado pelos testes */
    public static function reset(): void
    {
        self::$started = false;
        self::$record = null;
        self::$token = '';
        self::$dirty = [];
        self::$regenerate = false;
        self::$degraded = false;
    }
}
