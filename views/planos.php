<?php
/**
 * Planos e preços — lidos de config/app.php (constantes).
 *
 * @var array<int,array<string,mixed>> $plans
 * @var array<string,mixed>|null $addon
 */

$brl = static fn (int|string $cents): string => 'R$ ' . number_format(((int) $cents) / 100, 2, ',', '.');
?>
<section class="hero">
    <div class="container">
        <p class="badge">Preços direto da configuração</p>
        <h1>Planos do Freebuff</h1>
        <p class="lead">
            Pacotes avulsos para quem tem um álbum pontual; assinaturas para quem usa todo mês.
            Vídeo é add-on no checkout.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid">
            <?php foreach ($plans as $plan): ?>
                <article class="card">
                    <h3><?= e($plan['name']) ?></h3>
                    <p class="price">
                        <?= e($brl((int) $plan['price_cents'])) ?>
                        <small><?= $plan['period'] === 'once' ? 'pagamento único' : '/ ' . ($plan['period'] === 'month' ? 'mês' : 'ano') ?></small>
                    </p>
                    <p><?= e($plan['description'] ?? '') ?></p>

                    <table class="data">
                        <caption class="visually-hidden">Condições de <?= e($plan['name']) ?></caption>
                        <tbody>
                            <?php if ($plan['photos_per_day'] !== null): ?>
                                <tr>
                                    <th scope="row">Fotos por dia</th>
                                    <td><?= (int) $plan['photos_per_day'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Vídeos por dia</th>
                                    <td><?= (int) $plan['videos_per_day'] ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th scope="row">Fotos incluídas</th>
                                    <td><?= (int) $plan['photos_total'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Vídeo incluso</th>
                                    <td><?= $plan['video_included'] ? 'sim' : 'não (add-on)' ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th scope="row">Validade do álbum</th>
                                <td><?= (int) $plan['valid_days'] ?> dias</td>
                            </tr>
                        </tbody>
                    </table>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (is_array($addon) && !empty($addon['enabled'])): ?>
            <p class="card" style="margin-top:1.25rem">
                <strong><?= e($addon['name']) ?>:</strong>
                <?= e($brl((int) $addon['price_cents'])) ?>
                por <?= (int) $addon['videos'] ?> vídeos — disponível como <em>order bump</em> no checkout.
            </p>
        <?php endif; ?>
    </div>
</section>
