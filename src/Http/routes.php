<?php

declare(strict_types=1);

/**
 * Rotas do Freebuff.
 *
 * Arquivo carregado pelo front controller: recebe o Router e registra tudo.
 * Middlewares de autenticação/quota entram aqui nas fases seguintes.
 */

use Freebuff\Core\Config;
use Freebuff\Core\Database;
use Freebuff\Core\Migrator;
use Freebuff\Core\Request;
use Freebuff\Core\Response;
use Freebuff\Core\Router;

return static function (Router $router): void {
    /* Página inicial — home institucional do SaaS. */
    $router->get('/', static function (Request $request): Response {
        $dbOk = Database::isAvailable();

        $migrations = [];
        if ($dbOk) {
            try {
                $migrations = Migrator::status();
            } catch (\Throwable) {
                $migrations = [];
            }
        }

        $plans = [];

        foreach ((array) Config::get('plans.order', []) as $slug) {
            $plan = Config::get('plans.items.' . $slug);

            if (is_array($plan)) {
                $plans[] = $plan;
            }
        }

        return Response::html(
            \Freebuff\Core\View::render('home', [
                'title' => 'Freebuff — fotos antigas restauradas e vivas',
                'db_ok' => $dbOk,
                'migrations' => $migrations,
                'plans' => $plans,
                'provider' => Config::get('ai.provider'),
                'storage' => Config::get('storage.driver'),
                'payment' => Config::get('payment.driver'),
            ])
        );
    });

    /* Health check — usado pelo healthcheck do Docker e por monitoramento. */
    $router->get('/health', static function (Request $request): Response {
        $dbOk = Database::isAvailable();

        return Response::json([
            'status' => $dbOk ? 'ok' : 'degraded',
            'app' => Config::get('app.name'),
            'env' => Config::get('app.env'),
            'php' => PHP_VERSION,
            'database' => [
                'reachable' => $dbOk,
                'driver' => $dbOk ? Database::driver() : null,
            ],
            'ai_provider' => Config::get('ai.provider'),
            'storage' => Config::get('storage.driver'),
            'time' => date('c'),
        ], $dbOk ? 200 : 503);
    });

    /* Planos — lê direto das constantes de config/app.php. */
    $router->get('/planos', static function (Request $request): Response {
        $plans = [];

        foreach ((array) Config::get('plans.order', []) as $slug) {
            $plan = Config::get('plans.items.' . $slug);

            if (is_array($plan)) {
                $plan['slug'] = $slug;
                $plans[] = $plan;
            }
        }

        return Response::html(
            \Freebuff\Core\View::render('planos', [
                'title' => 'Planos e preços — Freebuff',
                'plans' => $plans,
                'addon' => Config::get('plans.addon_video_pack'),
            ])
        );
    });
};
