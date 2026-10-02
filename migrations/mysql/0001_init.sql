-- Freebuff — esquema inicial (MySQL/MariaDB / Hostinger)
-- Ordem respeita as chaves estrangeiras.

CREATE TABLE IF NOT EXISTS plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'pack',
    price_cents INT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    period VARCHAR(10) NOT NULL DEFAULT 'once',
    photos_total INT NULL,
    photos_per_day INT NULL,
    videos_per_day INT NULL,
    video_included TINYINT(1) NOT NULL DEFAULT 0,
    valid_days INT NOT NULL DEFAULT 365,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_plans_slug (slug),
    KEY idx_plans_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(191) NOT NULL DEFAULT '',
    role VARCHAR(20) NOT NULL DEFAULT 'customer',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    plan_id BIGINT UNSIGNED NULL,
    plan_expires_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_role (role, status),
    CONSTRAINT fk_users_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    data TEXT NULL,
    last_seen_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sessions_token (token_hash),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expires (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'one_time',
    amount_cents INT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    provider VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
    preference_id VARCHAR(191) NULL,
    provider_order_id VARCHAR(191) NULL,
    bump_cents INT NOT NULL DEFAULT 0,
    bump_videos INT NOT NULL DEFAULT 0,
    metadata TEXT NULL,
    paid_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (status, expires_at),
    KEY idx_orders_preference (preference_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_payment_id VARCHAR(191) NOT NULL,
    method VARCHAR(30) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    amount_cents INT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    installments INT NOT NULL DEFAULT 1,
    raw TEXT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_payments_provider_id (provider, provider_payment_id),
    KEY idx_payments_order (order_id, status),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dedupe de webhooks: o mesmo evento nunca credita duas vezes.
CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(30) NOT NULL,
    event_key VARCHAR(191) NOT NULL,
    type VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'received',
    payload TEXT NULL,
    error TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_webhook_provider_key (provider, event_key),
    KEY idx_webhook_status (status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cota diária (scope='day') e saldo de pacote avulso (scope='pack').
-- order_id=0 nas cotas diárias para que o UNIQUE funcione nos dois escopos.
CREATE TABLE IF NOT EXISTS quotas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(10) NOT NULL,
    scope VARCHAR(10) NOT NULL DEFAULT 'day',
    day DATE NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    limit_count INT NOT NULL DEFAULT 0,
    used INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_quota_bucket (user_id, kind, scope, day, order_id),
    KEY idx_quota_lookup (user_id, kind, day),
    CONSTRAINT fk_quotas_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS albums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(64) NOT NULL,
    title VARCHAR(191) NOT NULL DEFAULT 'Meu álbum',
    description TEXT NULL,
    cover_photo_id BIGINT UNSIGNED NULL,
    show_original_default TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_albums_slug (slug),
    KEY idx_albums_user (user_id, sort_order),
    CONSTRAINT fk_albums_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    album_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(191) NULL,
    original_key VARCHAR(255) NULL,
    original_url VARCHAR(500) NOT NULL,
    restored_key VARCHAR(255) NULL,
    restored_url VARCHAR(500) NULL,
    video_key VARCHAR(255) NULL,
    video_url VARCHAR(500) NULL,
    width INT NULL,
    height INT NULL,
    bytes_original INT NULL,
    bytes_restored INT NULL,
    bytes_video INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    video_status VARCHAR(20) NOT NULL DEFAULT 'none',
    error TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_photos_album (album_id, sort_order),
    KEY idx_photos_user_status (user_id, status),
    CONSTRAINT fk_photos_album FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila: claim por (status, priority, locked_until). O worker renova a trava
-- em vez de usar SKIP LOCKED, compatível com MySQL 5.7 e MariaDB.
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type VARCHAR(40) NOT NULL,
    photo_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    payload TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    locked_until DATETIME NULL,
    lock_token VARCHAR(64) NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(191) NULL,
    external_id VARCHAR(191) NULL,
    error TEXT NULL,
    priority INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_jobs_claim (status, priority, locked_until),
    KEY idx_jobs_photo (photo_id, type),
    KEY idx_jobs_user (user_id, status),
    CONSTRAINT fk_jobs_photo FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custo real por chamada de IA — base do painel de margem por cliente.
CREATE TABLE IF NOT EXISTS api_costs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    job_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    model VARCHAR(191) NOT NULL,
    operation VARCHAR(30) NOT NULL,
    units INT NOT NULL DEFAULT 1,
    unit_type VARCHAR(20) NOT NULL DEFAULT 'image',
    cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    cost_brl DECIMAL(12,4) NULL,
    duration_ms INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ok',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_api_costs_user (user_id, created_at),
    KEY idx_api_costs_day (created_at),
    CONSTRAINT fk_api_costs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_costs_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(191) NOT NULL,
    value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
