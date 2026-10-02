<?php

declare(strict_types=1);

/**
 * Freebuff — configuração central.
 *
 * Toda decisão de negócio numérica mora aqui: preços dos planos, cotas
 * diárias e modelos de IA são constantes, para que preço/cota possam ser
 * alterados sem tocar em código (risco #1 do plano: margem da assinatura).
 *
 * Não há dependências externas neste arquivo — é PHP puro.
 */

use Freebuff\Core\Config as _Config;
use Freebuff\Core\Env;

/* ------------------------------------------------------------------ *
 * Planos — preços em centavos (BRL) e cotas
 * ------------------------------------------------------------------ */

/* Pacote avulso: 10 fotos restauradas — R$ 150 */
define('PLAN_PACK_10_SLUG', 'pack_10');
define('PLAN_PACK_10_PRICE_CENTS', 15000);
define('PLAN_PACK_10_PHOTOS', 10);
define('PLAN_PACK_10_VIDEO_INCLUDED', false);
define('PLAN_PACK_10_VALID_DAYS', 365);

/* Pacote avulso: 20 fotos restauradas — R$ 300 */
define('PLAN_PACK_20_SLUG', 'pack_20');
define('PLAN_PACK_20_PRICE_CENTS', 30000);
define('PLAN_PACK_20_PHOTOS', 20);
define('PLAN_PACK_20_VIDEO_INCLUDED', false);
define('PLAN_PACK_20_VALID_DAYS', 365);

/* Assinatura mensal — R$ 100/mês, 20 fotos + 5 vídeos por dia */
define('PLAN_SUB_MONTHLY_SLUG', 'sub_monthly');
define('PLAN_SUB_MONTHLY_PRICE_CENTS', 10000);
define('PLAN_SUB_MONTHLY_PHOTOS_PER_DAY', 20);
define('PLAN_SUB_MONTHLY_VIDEOS_PER_DAY', 5);

/* Assinatura anual — R$ 800/ano (33% de desconto sobre 12x R$100) */
define('PLAN_SUB_YEARLY_SLUG', 'sub_yearly');
define('PLAN_SUB_YEARLY_PRICE_CENTS', 80000);
define('PLAN_SUB_YEARLY_PHOTOS_PER_DAY', 20);
define('PLAN_SUB_YEARLY_VIDEOS_PER_DAY', 5);

/* Vídeo como add-on comprado no checkout (order bump) */
define('ADDON_VIDEO_PACK_PRICE_CENTS', 3000); /* 10 vídeos avulsos — R$ 30 */
define('ADDON_VIDEO_PACK_VIDEOS', 10);

/* ------------------------------------------------------------------ *
 * Modelos de IA e custo por unidade (US$) — medir com bin/probe.php
 * ------------------------------------------------------------------ */

define('AI_PROVIDER_DEFAULT', 'mock'); /* mock | replicate | huggingface | comfyui */

/* Restauração + colorização em um único passo (mais caro, melhor qualidade) */
define('AI_RESTORE_PREMIUM_MODEL', 'flux-kontext-apps/restore-image');
define('AI_RESTORE_PREMIUM_COST_USD', 0.04);

/* Restauração econômica (assinatura, alto volume) */
define('AI_RESTORE_BASIC_MODEL', 'microsoft/bringing-old-photos-back-to-life');
define('AI_RESTORE_BASIC_COST_USD', 0.0097);

/* Colorização separada (usada quando separamos os dois passos) */
define('AI_COLORIZE_MODEL', 'piddnad/ddcolor');
define('AI_COLORIZE_COST_USD', 0.01);

/* Imagem -> vídeo (o mais barato do catálogo Replicate) */
define('AI_I2V_MODEL', 'wan-video/wan-2.2-i2v-fast');
define('AI_I2V_COST_USD', 0.10); /* placeholder: medir com bin/probe.php */
define('AI_I2V_DURATION_SECONDS', 5);
define('AI_I2V_RESOLUTION', '480p');

/* ------------------------------------------------------------------ *
 * Upload / limites operacionais
 * ------------------------------------------------------------------ */

define('UPLOAD_MAX_BYTES', 32 * 1024 * 1024); /* 32MB (upload_max_filesize) */
define('UPLOAD_MAX_DIMENSION', 4000);         /* px, maior lado após resize */
define('UPLOAD_BATCH_MAX', 50);               /* fotos por envio em lote */
define('PHOTO_WEBP_QUALITY', 86);
define('PHOTO_JPEG_QUALITY', 90);

/* ------------------------------------------------------------------ *
 * Fila / worker (Hostinger não tem shell nem queue)
 * ------------------------------------------------------------------ */

define('QUEUE_MAX_ATTEMPTS', 3);
define('QUEUE_LOCK_SECONDS', 300);  /* trava de um job em execução */
define('WORKER_MAX_JOBS_DEFAULT', 10);
define('WORKER_TIME_BUDGET_SECONDS', 90); /* fica abaixo do max_execution_time da Hostinger */

/* ------------------------------------------------------------------ *
 * Valores dinâmicos (env) — agregados num array consumido por Config
 * ------------------------------------------------------------------ */

$plans = [
    PLAN_PACK_10_SLUG => [
        'slug' => PLAN_PACK_10_SLUG,
        'name' => 'Pacote 10 fotos',
        'kind' => 'pack',
        'price_cents' => PLAN_PACK_10_PRICE_CENTS,
        'currency' => 'BRL',
        'period' => 'once',
        'photos_total' => PLAN_PACK_10_PHOTOS,
        'photos_per_day' => null,
        'videos_per_day' => 0,
        'video_included' => PLAN_PACK_10_VIDEO_INCLUDED,
        'valid_days' => PLAN_PACK_10_VALID_DAYS,
        'description' => 'Restauração + colorização de 10 fotos antigas, álbum compartilhável por 12 meses.',
    ],
    PLAN_PACK_20_SLUG => [
        'slug' => PLAN_PACK_20_SLUG,
        'name' => 'Pacote 20 fotos',
        'kind' => 'pack',
        'price_cents' => PLAN_PACK_20_PRICE_CENTS,
        'currency' => 'BRL',
        'period' => 'once',
        'photos_total' => PLAN_PACK_20_PHOTOS,
        'photos_per_day' => null,
        'videos_per_day' => 0,
        'video_included' => PLAN_PACK_20_VIDEO_INCLUDED,
        'valid_days' => PLAN_PACK_20_VALID_DAYS,
        'description' => 'Restauração + colorização de 20 fotos antigas, álbum compartilhável por 12 meses.',
    ],
    PLAN_SUB_MONTHLY_SLUG => [
        'slug' => PLAN_SUB_MONTHLY_SLUG,
        'name' => 'Assinatura mensal',
        'kind' => 'subscription',
        'price_cents' => PLAN_SUB_MONTHLY_PRICE_CENTS,
        'currency' => 'BRL',
        'period' => 'month',
        'photos_total' => null,
        'photos_per_day' => PLAN_SUB_MONTHLY_PHOTOS_PER_DAY,
        'videos_per_day' => PLAN_SUB_MONTHLY_VIDEOS_PER_DAY,
        'video_included' => true,
        'valid_days' => 30,
        'description' => '20 fotos restauradas por dia e 5 vídeos por dia. Cancele quando quiser.',
    ],
    PLAN_SUB_YEARLY_SLUG => [
        'slug' => PLAN_SUB_YEARLY_SLUG,
        'name' => 'Assinatura anual',
        'kind' => 'subscription',
        'price_cents' => PLAN_SUB_YEARLY_PRICE_CENTS,
        'currency' => 'BRL',
        'period' => 'year',
        'photos_total' => null,
        'photos_per_day' => PLAN_SUB_YEARLY_PHOTOS_PER_DAY,
        'videos_per_day' => PLAN_SUB_YEARLY_VIDEOS_PER_DAY,
        'video_included' => true,
        'valid_days' => 365,
        'description' => '20 fotos restauradas por dia e 5 vídeos por dia, pagando 12 meses à vista.',
    ],
];

$planOrder = [
    PLAN_PACK_10_SLUG,
    PLAN_PACK_20_SLUG,
    PLAN_SUB_MONTHLY_SLUG,
    PLAN_SUB_YEARLY_SLUG,
];

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Freebuff'),
        'env' => Env::get('APP_ENV', 'local'),
        'debug' => Env::get('APP_DEBUG', true),
        'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
        'locale' => Env::get('APP_LOCALE', 'pt_BR'),
        'currency' => 'BRL',
        'locale_format' => 'pt_BR',
    ],

    'db' => [
        'driver' => Env::get('DB_DRIVER', 'mysql'),
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'freebuff'),
        'user' => Env::get('DB_USER', 'freebuff'),
        'pass' => (string) Env::get('DB_PASS', ''),
        'charset' => 'utf8mb4',
        'sqlite_path' => Env::get(
            'DB_SQLITE_PATH',
            dirname(__DIR__) . '/storage/database/freebuff.sqlite'
        ),
    ],

    'security' => [
        'session_cookie' => 'freebuff_session',
        'session_lifetime_days' => (int) Env::get('SESSION_DAYS', 30),
        'csrf_key' => '_csrf',
        /* Prefixedos que não passam por CSRF (webhooks de pagamento). */
        'csrf_except' => ['/webhook/'],
        'bcrypt_cost' => 12,
    ],

    /* Planos: lista ordenada + lookup por slug (ambos derivados das constantes) */
    'plans' => [
        'order' => $planOrder,
        'items' => $plans,
        'addon_video_pack' => [
            'slug' => 'addon_video_pack',
            'name' => 'Add-on: 10 vídeos',
            'price_cents' => ADDON_VIDEO_PACK_PRICE_CENTS,
            'videos' => ADDON_VIDEO_PACK_VIDEOS,
            'enabled' => true,
        ],
    ],

    'ai' => [
        'provider' => Env::get('AI_PROVIDER', AI_PROVIDER_DEFAULT),
        'timeout_seconds' => (int) Env::get('AI_TIMEOUT', 90),
        'preset' => Env::get('AI_PRESET', 'basic'), /* premium | basic */

        'models' => [
            'restore_premium' => [
                'model' => AI_RESTORE_PREMIUM_MODEL,
                'cost_usd' => AI_RESTORE_PREMIUM_COST_USD,
                'operation' => 'restore',
                'unit' => 'image',
            ],
            'restore_basic' => [
                'model' => AI_RESTORE_BASIC_MODEL,
                'cost_usd' => AI_RESTORE_BASIC_COST_USD,
                'operation' => 'restore',
                'unit' => 'image',
            ],
            'colorize' => [
                'model' => AI_COLORIZE_MODEL,
                'cost_usd' => AI_COLORIZE_COST_USD,
                'operation' => 'colorize',
                'unit' => 'image',
            ],
            'i2v' => [
                'model' => AI_I2V_MODEL,
                'cost_usd' => AI_I2V_COST_USD,
                'operation' => 'video',
                'unit' => 'video',
                'duration_seconds' => AI_I2V_DURATION_SECONDS,
                'resolution' => AI_I2V_RESOLUTION,
            ],
        ],

        'providers' => [
            'replicate' => [
                'base_url' => 'https://api.replicate.com',
                'token' => Env::get('REPLICATE_API_TOKEN', ''),
            ],
            'huggingface' => [
                'base_url' => 'https://router.huggingface.co',
                'token' => Env::get('HF_TOKEN', ''),
            ],
            'comfyui' => [
                'base_url' => rtrim((string) Env::get('COMFYUI_URL', 'http://host.docker.internal:8188'), '/'),
                'token' => Env::get('COMFYUI_TOKEN', ''),
            ],
            'mock' => [
                'base_url' => '',
                'token' => 'mock',
            ],
        ],
    ],

    'storage' => [
        'driver' => Env::get('STORAGE_DRIVER', 'local'),
        'local' => [
            'path' => Env::get('STORAGE_LOCAL_PATH', dirname(__DIR__) . '/storage/uploads'),
            /* URL pública relativa ao DocumentRoot (public/) */
            'base_url' => '/media',
        ],
        'r2' => [
            'account_id' => Env::get('R2_ACCOUNT_ID', ''),
            'bucket' => Env::get('R2_BUCKET', ''),
            'access_key_id' => Env::get('R2_ACCESS_KEY_ID', ''),
            'secret_access_key' => Env::get('R2_SECRET_ACCESS_KEY', ''),
            'region' => 'auto',
            /* Cloudflare R2 não expõe endpoint regional padrão */
            'endpoint' => Env::get(
                'R2_ENDPOINT',
                'https://' . (string) Env::get('R2_ACCOUNT_ID', '') . '.r2.cloudflarestorage.com'
            ),
            'public_url' => rtrim((string) Env::get('R2_PUBLIC_URL', ''), '/'),
        ],
    ],

    'payment' => [
        'driver' => Env::get('PAYMENT_DRIVER', 'mercadopago'),
        'currency' => 'BRL',
        'mercadopago' => [
            'environment' => Env::get('MP_ENV', 'test'),
            'public_key' => Env::get('MP_PUBLIC_KEY', ''),
            'access_token' => Env::get('MP_ACCESS_TOKEN', ''),
            'webhook_secret' => Env::get('MP_WEBHOOK_SECRET', ''),
            'base_url' => 'https://api.mercadopago.com',
            'success_path' => '/pagamento/sucesso',
            'back_path' => '/painel',
        ],
        'hero' => [
            'checkout_url' => Env::get('HERO_CHECKOUT_URL', ''),
        ],
        'manual' => [],
    ],

    'queue' => [
        'max_attempts' => QUEUE_MAX_ATTEMPTS,
        'lock_seconds' => QUEUE_LOCK_SECONDS,
        'worker_max_jobs' => WORKER_MAX_JOBS_DEFAULT,
        'time_budget_seconds' => WORKER_TIME_BUDGET_SECONDS,
    ],

    'upload' => [
        'max_bytes' => UPLOAD_MAX_BYTES,
        'max_dimension' => UPLOAD_MAX_DIMENSION,
        'batch_max' => UPLOAD_BATCH_MAX,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/heic'],
    ],

    'limits' => [
        'login_attempts' => 5,
        'login_window_seconds' => 900,
    ],

    'log' => [
        'path' => dirname(__DIR__) . '/storage/logs',
        'level' => Env::get('LOG_LEVEL', 'debug'),
    ],
];
