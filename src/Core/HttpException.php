<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Erro de aplicação com status HTTP (404, 403, 419...).
 * O front controller captura e converte em Response.
 */
final class HttpException extends \RuntimeException
{
    /** @param array<string,mixed> $context */
    public function __construct(
        int $status,
        string $message = '',
        private array $context = [],
        private ?Response $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Acesso negado.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Faça login para continuar.'): self
    {
        return new self(401, $message);
    }

    public static function conflict(string $message = ''): self
    {
        return new self(409, $message);
    }

    public static function unprocessable(string $message = ''): self
    {
        return new self(422, $message);
    }

    public static function tooManyRequests(string $message = 'Muitas tentativas. Tente novamente em instantes.'): self
    {
        return new self(429, $message);
    }

    /**
     * Token CSRF ausente/inválido.
     *
     * Usamos 400 e não 419 (código não padronizado, criado pelo Laravel):
     * Apache responde 500 para status desconhecidos — verificado no Docker.
     */
    public static function csrf(string $message = 'Sessão expirada. Recarregue a página e tente novamente.'): self
    {
        return new self(400, $message);
    }

    public static function badRequest(string $message = ''): self
    {
        return new self(400, $message);
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function toResponse(Request $request): Response
    {
        if ($this->response !== null) {
            return $this->response;
        }

        if ($request->wantsJson()) {
            return Response::json(
                ['error' => $this->getMessage(), 'status' => $this->getCode()],
                $this->getCode()
            );
        }

        return Response::html(
            View::render('errors/error', [
                'status' => $this->getCode(),
                'title' => self::defaultMessage($this->getCode()),
                'message' => $this->getMessage(),
            ]),
            $this->getCode()
        );
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida.',
            401 => 'Autenticação necessária.',
            403 => 'Acesso negado.',
            404 => 'Página não encontrada.',
            409 => 'Conflito de estado.',
            422 => 'Dados inválidos.',
            429 => 'Muitas tentativas.',
            default => 'Ocorreu um erro.',
        };
    }
}
