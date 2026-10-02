<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Router: registro de rotas com parâmetros `{id}` e pipeline de middleware.
 *
 *   $router->get('/album/{slug}', fn (Request $r, array $p) => ...);
 */
final class Router
{
    /** @var array<int,array<string,mixed>> */
    private array $routes = [];

    /** @var array<int,string> */
    private array $groupPrefix = [];

    /** @var array<int,array<int,callable>> */
    private array $groupMiddleware = [];

    private string $prefix = '';

    /** @var array<int,callable> */
    private array $stack = [];

    public function get(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    public function any(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('ANY', $path, $handler, $middleware);
    }

    /**
     * Agrupa rotas sob um prefixo com middlewares compartilhados.
     *
     *   $router->group('/admin', [fn ($r, $next) => $next($r)], function (Router $r) { ... });
     */
    public function group(string $prefix, callable|array $middleware, callable $register): self
    {
        $middleware = is_array($middleware) ? array_values($middleware) : [$middleware];

        $previousPrefix = $this->prefix;
        $previousStack = $this->stack;

        $this->prefix = rtrim($previousPrefix . $prefix, '/');
        $this->stack = array_merge($previousStack, $middleware);

        $register($this);

        $this->prefix = $previousPrefix;
        $this->stack = $previousStack;

        return $this;
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): self
    {
        $full = $this->prefix . ($path === '/' ? '/' : $path);
        $full = Request::normalizePath($full === '' ? '/' : $full);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $full,
            'handler' => $handler,
            'middleware' => array_merge($this->stack, array_values($middleware)),
        ];

        return $this;
    }

    /** @return array<int,array<string,mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = [];

            if (!$this->match($route['pattern'], $path, $params)) {
                continue;
            }

            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                $allowed[] = $route['method'];

                continue;
            }

            return $this->run($route, $request, $params);
        }

        if ($allowed !== []) {
            return Response::json(
                ['error' => 'Método não permitido', 'allow' => array_values(array_unique($allowed))],
                405
            )->withHeader('Allow', implode(', ', array_unique($allowed)));
        }

        return $this->notFound($request);
    }

    private function match(string $pattern, string $path, array &$params): bool
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return false;
        }

        foreach ($patternParts as $i => $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $m) === 1) {
                $regex = $m[2] ?? '[^/]+';

                if (preg_match('#^(' . $regex . ')$#', $pathParts[$i], $matched) !== 1) {
                    return false;
                }

                $params[$m[1]] = $matched[1];

                continue;
            }

            if ($segment !== $pathParts[$i]) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $route */
    private function run(array $route, Request $request, array $params): Response
    {
        $handler = static fn (Request $r): Response => self::normalizeResult(
            ($route['handler'])($r, $params)
        );

        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            static fn (callable $next, callable $mw): callable => static fn (Request $r): Response => $mw($r, $next),
            $handler
        );

        return $pipeline($request);
    }

    private static function normalizeResult(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if ($result === null) {
            return Response::noContent();
        }

        throw new \UnexpectedValueException('O handler da rota retornou tipo não suportado: ' . get_debug_type($result));
    }

    private function notFound(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => 'Rota não encontrada', 'path' => $request->path()], 404);
        }

        return Response::html(View::render('errors/404', ['path' => $request->path()]), 404);
    }
}
