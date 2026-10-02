<?php

declare(strict_types=1);

namespace Freebuff\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Acesso a dados via PDO. Suporta MySQL/MariaDB (produção) e SQLite
 * (testes unitários, sem servidor). Sem dependências externas.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static string $driver = '';

    private static int $transactions = 0;

    public static function boot(?array $config = null): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $config ??= Config::get('db');
        $driver = strtolower((string) ($config['driver'] ?? 'mysql'));

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $path = (string) ($config['sqlite_path'] ?? ':memory:');

                if ($path !== ':memory:') {
                    $dir = dirname($path);

                    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                        throw new RuntimeException('Não foi possível criar o diretório do SQLite: ' . $dir);
                    }
                }

                $pdo = new PDO('sqlite:' . $path, null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('PRAGMA busy_timeout = 5000');
                self::$driver = 'sqlite';
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $config['host'],
                    (int) $config['port'],
                    $config['name'],
                    $config['charset'] ?? 'utf8mb4'
                );

                $pdo = new PDO($dsn, (string) $config['user'], (string) $config['pass'], $options);
                $pdo->exec("SET time_zone = '-03:00'");
                self::$driver = 'mysql';
            }
        } catch (PDOException $e) {
            throw $e;
        }

        self::$pdo = $pdo;
        self::$transactions = 0;
    }

    public static function pdo(): PDO
    {
        self::boot();

        return self::$pdo;
    }

    public static function driver(): string
    {
        self::boot();

        return self::$driver;
    }

    public static function isAvailable(): bool
    {
        try {
            self::boot();
            self::query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Insere uma linha e devolve o id gerado (0 no SQLite quando sem AUTOINCREMENT).
     */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        self::query($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Atualiza uma linha e devolve o número de linhas afetadas.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($data)));

        return self::query(
            sprintf('UPDATE %s SET %s WHERE %s', $table, $set, $where),
            $data + $whereParams
        )->rowCount();
    }

    /**
     * Executa $fn dentro de uma transação, dando rollback em caso de exceção.
     * Suporta transações aninhadas (contador interno, apenas o externo dá commit).
     */
    public static function transaction(callable $fn): mixed
    {
        self::boot();

        $outer = self::$transactions === 0;

        if ($outer) {
            self::pdo()->beginTransaction();
        }

        self::$transactions++;

        try {
            $result = $fn();
            self::$transactions--;

            /* DDL no MySQL faz commit implícito: só commita se ainda houver tx. */
            if ($outer && self::pdo()->inTransaction()) {
                self::pdo()->commit();
            }

            return $result;
        } catch (Throwable $e) {
            self::$transactions--;

            if ($outer && self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }

            throw $e;
        }
    }

    public static function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException('Identificador SQL inválido: ' . $identifier);
        }

        return self::$driver === 'sqlite' ? '"' . $identifier . '"' : '`' . $identifier . '`';
    }

    /**
     * Fecha a conexão (usado pelos testes para trocar de banco).
     */
    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$driver = '';
        self::$transactions = 0;
    }
}
