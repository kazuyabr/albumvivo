<?php
/**
 * Erro genérico (400/403/419/422/429/500).
 *
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string|null $trace
 */
?>
<section class="error-page container">
    <p class="code"><?= (int) ($status ?? 500) ?></p>
    <h1><?= e($title ?? 'Erro') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <?php if (!empty($trace)): ?>
        <pre class="trace"><?= e($trace) ?></pre>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="/">Voltar ao início</a></p>
</section>
