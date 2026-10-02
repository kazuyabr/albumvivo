<?php

declare(strict_types=1);

/**
 * Freebuff — front controller.
 *
 * Todo request (exceto arquivos estáticos) entra aqui via .htaccess.
 */

use Freebuff\Core\Config;
use Freebuff\Core\Csrf;
use Freebuff\Core\HttpException;
use Freebuff\Core\Log;
use Freebuff\Core\Request;
use Freebuff\Core\Response;
use Freebuff\Core\Router;
use Freebuff\Core\Session;
use Freebuff\Core\View;

require __DIR__ . '/../src/autoload.php';

Config::boot();
Log::install();

$router = new Router();
(require __DIR__ . '/../src/Http/routes.php')($router);

$request = Request::fromGlobals();

try {
    Session::start();
} catch (\Throwable $e) {
    Log::warning('Sessão indisponível: ' . $e->getMessage());
}

try {
    /* Webhooks de pagamento não têm token CSRF: eles não vêm do navegador. */
    $exempt = false;

    foreach ((array) Config::get('security.csrf_except', []) as $prefix) {
        if (is_string($prefix) && $prefix !== '' && str_starts_with($request->path(), $prefix)) {
            $exempt = true;
            break;
        }
    }

    if (!$exempt) {
        Csrf::assertValid($request);
    }

    $response = $router->dispatch($request);
} catch (HttpException $e) {
    Log::notice('HTTP ' . $e->getCode() . ': ' . $e->getMessage(), ['path' => $request->path()]);
    $response = $e->toResponse($request);
} catch (\Throwable $e) {
    Log::error($e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'path' => $request->path(),
    ]);

    $debug = (bool) Config::get('app.debug');

    $response = Response::html(
        View::render('errors/error', [
            'status' => 500,
            'title' => 'Erro no servidor',
            'message' => $debug
                ? $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()
                : 'Ocorreu um erro inesperado. Tente novamente em instantes.',
            'trace' => $debug ? $e->getTraceAsString() : null,
        ]),
        500
    );
}

$response = $response->withHeaders([
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
]);

$response->send();
