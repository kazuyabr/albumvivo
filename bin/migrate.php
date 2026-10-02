<?php

declare(strict_types=1);

/**
 * Freebuff — aplica as migrations do banco.
 *
 *   php bin/migrate.php            aplica o que estiver pendente
 *   php bin/migrate.php --status   mostra o estado de cada migration
 *
 * Usado no Docker (docker compose exec app php bin/migrate.php) e no cron
 * da Hostinger (não requer shell interativo).
 */

use Freebuff\Core\Config;
use Freebuff\Core\Database;
use Freebuff\Core\Log;
use Freebuff\Core\Migrator;

require __DIR__ . '/../src/autoload.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    http_response_code(403);
    echo 'Somente CLI.';
    exit(1);
}

Config::boot();
Log::install();

$wantStatus = in_array('--status', $argv, true);
$failed = false;

try {
    Database::boot();
    Migrator::migrate();

    if ($wantStatus) {
        fwrite(STDOUT, sprintf("Driver: %s\n\n", Database::driver()));

        foreach (Migrator::status() as $row) {
            fwrite(STDOUT, sprintf(
                "  [%s] %-28s %s%s\n",
                $row['applied'] ? 'OK' : '  ',
                $row['name'],
                $row['applied'] ? 'aplicada em ' . $row['applied_at'] : 'pendente',
                ($row['applied'] && !$row['checksum_ok']) ? '  (checksum divergente!)' : ''
            ));
        }

        exit(0);
    }

    $applied = Database::fetchAll('SELECT name, applied_at FROM migrations ORDER BY name');
    fwrite(STDOUT, sprintf("Driver: %s — migrations aplicadas: %d\n", Database::driver(), count($applied)));
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    fwrite(STDERR, '  em ' . $e->getFile() . ':' . $e->getLine() . "\n");
}

exit($failed ? 1 : 0);
