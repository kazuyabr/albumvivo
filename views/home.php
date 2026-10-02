<?php
/**
 * Home institucional + painel de status da fundação.
 *
 * @var bool $db_ok
 * @var array<int,array<string,mixed>> $migrations
 * @var array<int,array<string,mixed>> $plans
 * @var string $provider
 * @var string $storage
 * @var string $payment
 */

$applied = count(array_filter($migrations, static fn (array $m): bool => (bool) $m['applied']));
$total = count($migrations);
$pending = $total - $applied;
?>
<section class="hero">
    <div class="container">
        <p class="badge">Restauração autônoma · Álbum flipbook · Vídeo a partir da foto</p>
        <h1>Fotos antigas voltam a ser <em>fotos vivas</em></h1>
        <p class="lead">
            O Freebuff restaura e coloriza fotos antigas sozinho, monta um álbum interativo que
            vira as páginas como um livro e anima cada retrato em vídeo — tudo compartilhável e
            baixável.
        </p>
        <p>
            <a class="btn btn-primary" href="/planos">Ver planos</a>
            <a class="btn btn-secondary" href="/health">Status do sistema</a>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>Planos</h2>
        <div class="grid">
            <?php foreach ($plans as $plan): ?>
                <article class="card">
                    <h3><?= e($plan['name']) ?></h3>
                    <p class="price">
                        R$ <?= e(number_format(((int) $plan['price_cents']) / 100, 2, ',', '.')) ?>
                        <small><?= $plan['period'] === 'once' ? 'pagamento único' : '/' . e($plan['period']) ?></small>
                    </p>
                    <p><?= e($plan['description'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>Status da fundação</h2>
        <ul class="status-list">
            <li>
                <span>Banco de dados</span>
                <span class="<?= $db_ok ? 'ok' : 'warn' ?>">
                    <?= $db_ok ? 'conectado (' . e(\Freebuff\Core\Database::driver()) . ')' : 'indisponível' ?>
                </span>
            </li>
            <li>
                <span>Migrations</span>
                <span class="<?= $pending === 0 && $total > 0 ? 'ok' : 'warn' ?>">
                    <?= $total === 0 ? 'nenhuma encontrada' : $applied . '/' . $total . ' aplicadas' . ($pending > 0 ? ' (' . $pending . ' pendente(s))' : '') ?>
                </span>
            </li>
            <li><span>Provedor de IA</span><strong><?= e($provider) ?></strong></li>
            <li><span>Armazenamento</span><strong><?= e($storage) ?></strong></li>
            <li><span>Pagamentos</span><strong><?= e($payment) ?></strong></li>
        </ul>
    </div>
</section>
