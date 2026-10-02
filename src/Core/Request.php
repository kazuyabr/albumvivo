<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Requisição HTTP imutável. Criada a partir dos superglobais, mas testável
 * de forma isolada (os testes constroem instâncias diretamente).
 */
final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     * @param array<string,string> $headers
     * @param array<string,mixed> $server
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $post = [],
        private array $files = [],
        private array $headers = [],
        private string $rawBody = '',
        private array $server = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $raw = file_get_contents('php://input') ?: '';

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $headers,
            $raw,
            $_SERVER
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->post + $this->query;
    }

    /** @return array<string,mixed> */
    public function post(): array
    {
        return $this->post;
    }

    /** @return array<string,mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->rawBody === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function ip(): string
    {
        $forwarded = $this->header('x-forwarded-for');

        if ($forwarded !== null && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if ($first !== '') {
                return $first;
            }
        }

        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? $this->header('user-agent', ''));
    }

    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest'
            || str_contains((string) $this->header('accept', ''), 'application/json');
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_starts_with($this->path, '/api/');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off'
            || $this->header('x-forwarded-proto') === 'https';
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->header('host', 'localhost');

        return $scheme . '://' . $host . $this->path;
    }

    public static function normalizePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
