<?php
/**
 * Erro 404.
 *
 * @var string $path
 */
?>
<section class="error-page container">
    <p class="code">404</p>
    <h1>Página não encontrada</h1>
    <p>O endereço <code><?= e($path ?? '') ?></code> não existe.</p>
    <p><a class="btn btn-primary" href="/">Voltar ao início</a></p>
</section>
