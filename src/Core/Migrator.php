<?php

declare(strict_types=1);

namespace Freebuff\Core;

/**
 * Runner de migrations: aplica migrations/*.sql em ordem, uma transação por
 * arquivo, registrando nome + checksum na tabela `migrations`.
 *
 * Suporta MySQL/MariaDB e SQLite (mesma pasta de origem, dialeto diferente),
 * com split de statements que respeita aspas e comentários — sem dependências.
 */
final class Migrator
{
    /** @return array<int,array<string,mixed>> migrations aplicadas nesta execução */
    public static function migrate(?string $dir = null): array
    {
        $dir ??= self::directory();
        self::ensureTable();
        $applied = [];

        foreach (self::files($dir) as $file) {
            $name = basename($file);

            if (self::has($name)) {
                continue;
            }

            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new \RuntimeException('Não foi possível ler a migration: ' . $file);
            }

            $checksum = hash('sha256', $sql);
            $statements = self::split($sql);

            /*
             * SQLite tem DDL transacional (rollback de verdade). MySQL/MariaDB
             * fazem commit implícito em cada CREATE TABLE, então ali a migration
             * roda statement a statement e a tabela `migrations` é a marca de
             * conclusão — o DDL usa IF NOT EXISTS para ser re-executável.
             */
            $apply = static function () use ($statements, $name, $checksum): void {
                foreach ($statements as $statement) {
                    Database::query($statement);
                }

                Database::insert('migrations', [
                    'name' => $name,
                    'checksum' => $checksum,
                    'applied_at' => date('Y-m-d H:i:s'),
                ]);
            };

            if (self::driver() === 'sqlite') {
                Database::transaction($apply);
            } else {
                $apply();
            }

            $applied[] = [
                'name' => $name,
                'statements' => count($statements),
                'checksum' => $checksum,
            ];
        }

        return $applied;
    }

    /** @return array<int,array<string,mixed>> */
    public static function status(?string $dir = null): array
    {
        $dir ??= self::directory();
        self::ensureTable();
        $rows = Database::fetchAll('SELECT name, checksum, applied_at FROM migrations ORDER BY name');
        $done = array_column($rows, null, 'name');

        $status = [];

        foreach (self::files($dir) as $file) {
            $name = basename($file);
            $sql = file_get_contents($file);
            $checksum = $sql === false ? '' : hash('sha256', $sql);

            $status[] = [
                'name' => $name,
                'applied' => isset($done[$name]),
                'pending' => !isset($done[$name]),
                'checksum_ok' => !isset($done[$name]) || $done[$name]['checksum'] === $checksum,
                'applied_at' => $done[$name]['applied_at'] ?? null,
            ];
        }

        return $status;
    }

    public static function has(string $name): bool
    {
        return Database::fetch('SELECT 1 FROM migrations WHERE name = ?', [$name]) !== null;
    }

    public static function directory(): string
    {
        $driver = self::driver();

        return dirname(__DIR__, 2) . '/migrations/' . ($driver === 'sqlite' ? 'sqlite' : 'mysql');
    }

    public static function driver(): string
    {
        try {
            return Database::driver();
        } catch (\Throwable) {
            return 'mysql';
        }
    }

    /** @return array<int,string> */
    private static function files(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException('Diretório de migrations inexistente: ' . $dir);
        }

        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    private static function ensureTable(): void
    {
        if (self::tableExists()) {
            return;
        }

        $isSqlite = self::driver() === 'sqlite';

        $sql = $isSqlite
            ? 'CREATE TABLE IF NOT EXISTS migrations (
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   name TEXT NOT NULL UNIQUE,
                   checksum CHAR(64) NULL,
                   applied_at TEXT NOT NULL
               )'
            : 'CREATE TABLE IF NOT EXISTS migrations (
                   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                   name VARCHAR(191) NOT NULL,
                   checksum CHAR(64) NULL,
                   applied_at DATETIME NOT NULL,
                   PRIMARY KEY (id),
                   UNIQUE KEY uniq_migrations_name (name)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        Database::query($sql);
    }

    private static function tableExists(): bool
    {
        try {
            if (self::driver() === 'sqlite') {
                return Database::fetch(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'"
                ) !== null;
            }

            return Database::fetch('SHOW TABLES LIKE ?', ['migrations']) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Divide um arquivo SQL em statements, respeitando aspas simples,
     * duplas, crase e os dois tipos de comentário SQL (linha e bloco).
     *
     * @return array<int,string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    /* 'texto''com aspas' */
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }

                    $quote = null;
                }

                continue;
            }

            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                $buffer .= "\n";
                continue;
            }

            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $i += 2;

                while ($i < $length && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                    $i++;
                }

                $i++;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trailing = trim($buffer);

        if ($trailing !== '') {
            $statements[] = $trailing;
        }

        return $statements;
    }
}
