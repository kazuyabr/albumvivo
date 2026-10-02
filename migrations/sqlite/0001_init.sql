-- Freebuff — esquema inicial (SQLite, testes unitários sem servidor)
-- Espelha migrations/mysql/0001_init.sql.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NULL,
    kind TEXT NOT NULL DEFAULT 'pack',
    price_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'BRL',
    period TEXT NOT NULL DEFAULT 'once',
    photos_total INTEGER NULL,
    photos_per_day INTEGER NULL,
    videos_per_day INTEGER NULL,
    video_included INTEGER NOT NULL DEFAULT 0,
    valid_days INTEGER NOT NULL DEFAULT 365,
    active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_plans_active ON plans (active, sort_order);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'customer',
    status TEXT NOT NULL DEFAULT 'active',
    plan_id INTEGER NULL REFERENCES plans (id) ON DELETE SET NULL,
    plan_expires_at TEXT NULL,
    last_login_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_role ON users (role, status);

CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    user_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
    ip TEXT NULL,
    user_agent TEXT NULL,
    data TEXT NULL,
    last_seen_at TEXT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions (expires_at);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    plan_id INTEGER NOT NULL REFERENCES plans (id) ON DELETE CASCADE,
    kind TEXT NOT NULL DEFAULT 'one_time',
    amount_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'BRL',
    status TEXT NOT NULL DEFAULT 'pending',
    provider TEXT NOT NULL DEFAULT 'mercadopago',
    preference_id TEXT NULL,
    provider_order_id TEXT NULL,
    bump_cents INTEGER NOT NULL DEFAULT 0,
    bump_videos INTEGER NOT NULL DEFAULT 0,
    metadata TEXT NULL,
    paid_at TEXT NULL,
    expires_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status, expires_at);
CREATE INDEX IF NOT EXISTS idx_orders_preference ON orders (preference_id);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    provider TEXT NOT NULL,
    provider_payment_id TEXT NOT NULL,
    method TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    amount_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'BRL',
    installments INTEGER NOT NULL DEFAULT 1,
    raw TEXT NULL,
    paid_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (provider, provider_payment_id)
);

CREATE INDEX IF NOT EXISTS idx_payments_order ON payments (order_id, status);

CREATE TABLE IF NOT EXISTS webhook_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL,
    event_key TEXT NOT NULL,
    type TEXT NULL,
    status TEXT NOT NULL DEFAULT 'received',
    payload TEXT NULL,
    error TEXT NULL,
    received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TEXT NULL,
    UNIQUE (provider, event_key)
);

CREATE INDEX IF NOT EXISTS idx_webhook_status ON webhook_events (status, received_at);

-- Cota diária (scope='day') e saldo de pacote avulso (scope='pack').
CREATE TABLE IF NOT EXISTS quotas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    scope TEXT NOT NULL DEFAULT 'day',
    day TEXT NOT NULL,
    order_id INTEGER NOT NULL DEFAULT 0,
    limit_count INTEGER NOT NULL DEFAULT 0,
    used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, kind, scope, day, order_id)
);

CREATE INDEX IF NOT EXISTS idx_quota_lookup ON quotas (user_id, kind, day);

CREATE TABLE IF NOT EXISTS albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL DEFAULT 'Meu álbum',
    description TEXT NULL,
    cover_photo_id INTEGER NULL,
    show_original_default INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'published',
    is_public INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_albums_user ON albums (user_id, sort_order);

CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    album_id INTEGER NOT NULL REFERENCES albums (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NULL,
    original_key TEXT NULL,
    original_url TEXT NOT NULL,
    restored_key TEXT NULL,
    restored_url TEXT NULL,
    video_key TEXT NULL,
    video_url TEXT NULL,
    width INTEGER NULL,
    height INTEGER NULL,
    bytes_original INTEGER NULL,
    bytes_restored INTEGER NULL,
    bytes_video INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    video_status TEXT NOT NULL DEFAULT 'none',
    error TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_photos_album ON photos (album_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_photos_user_status ON photos (user_id, status);

CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    photo_id INTEGER NOT NULL REFERENCES photos (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    payload TEXT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 3,
    locked_until TEXT NULL,
    lock_token TEXT NULL,
    provider TEXT NULL,
    model TEXT NULL,
    external_id TEXT NULL,
    error TEXT NULL,
    priority INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TEXT NULL,
    finished_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_jobs_claim ON jobs (status, priority, locked_until);
CREATE INDEX IF NOT EXISTS idx_jobs_photo ON jobs (photo_id, type);
CREATE INDEX IF NOT EXISTS idx_jobs_user ON jobs (user_id, status);

CREATE TABLE IF NOT EXISTS api_costs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
    job_id INTEGER NULL REFERENCES jobs (id) ON DELETE SET NULL,
    provider TEXT NOT NULL,
    model TEXT NOT NULL,
    operation TEXT NOT NULL,
    units INTEGER NOT NULL DEFAULT 1,
    unit_type TEXT NOT NULL DEFAULT 'image',
    cost_usd REAL NOT NULL DEFAULT 0,
    cost_brl REAL NULL,
    duration_ms INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'ok',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_api_costs_user ON api_costs (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_api_costs_day ON api_costs (created_at);

CREATE TABLE IF NOT EXISTS settings (
    name TEXT PRIMARY KEY,
    value TEXT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
