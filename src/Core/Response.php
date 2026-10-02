<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Resposta HTTP. O corpo é resolvido no momento do envio para permitir
 * streaming (download de ZIP do álbum) sem carregar tudo na memória.
 */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    private ?string $body = null;

    private mixed $stream = null;

    public function __construct(
        private int $status = 200,
        string $body = ''
    ) {
        $this->body = $body;
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($status, $body))
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '{}';

        return (new self($status, $body))
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($status, $body))
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return (new self($status))->withHeader('Location', $to);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /**
     * Resposta com corpo em streaming (callables retornam trechos).
     */
    public static function stream(callable $producer, int $status = 200): self
    {
        $response = new self($status);
        $response->stream = $producer;

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;

        foreach ($headers as $name => $value) {
            $clone->headers[$name] = $value;
        }

        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        if ($this->stream !== null) {
            ob_start();
            ($this->stream)();
            $this->stream = null;

            return (string) ob_get_clean();
        }

        return (string) $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            if ($this->header('Content-Type') === null) {
                header('Content-Type: text/html; charset=utf-8', true);
            }
        }

        if ($this->stream !== null) {
            $producer = $this->stream;
            $this->stream = null;
            $producer();

            return;
        }

        echo (string) $this->body;
    }
}
