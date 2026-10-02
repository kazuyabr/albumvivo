<?php
/**
 * Layout principal do Freebuff.
 *
 * @var string $content
 * @var string|null $title
 */

use Freebuff\Core\Config;

$appName = (string) Config::get('app.name', 'Freebuff');
$title = isset($title) ? (string) $title : $appName;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="Restauração autônoma de fotos antigas, álbuns interativos e vídeos a partir de fotos.">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/">
            <span class="brand-mark" aria-hidden="true">Fb</span>
            <span><?= e($appName) ?></span>
        </a>
        <nav class="nav" aria-label="Principal">
            <a href="/">Início</a>
            <a href="/planos">Planos</a>
            <a href="/health">Status</a>
        </nav>
    </div>
</header>

<main id="conteudo" class="site-main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">
        <p><?= e($appName) ?> — fotos antigas restauradas, coloridas e vivas. Feito em PHP puro.</p>
    </div>
</footer>
</body>
</html>
