<?php

/**
 * Layout público (login, registro, recuperação).
 *
 * @var string                 $siteName Nome do site (título da página)
 * @var string                 $content  HTML renderizado da view interna
 * @var array{type:string,message:string}|null $flash Mensagem flash (opcional)
 */

$siteName = $siteName ?? 'Caça ao Tesouro';
$flash = $flash ?? null;

// Cache-busting: versão automática pelo mtime do arquivo
$cssVersion = @filemtime(__DIR__ . '/../../public/assets/css/tailwind.css') ?: 1;
$jsVersion = @filemtime(__DIR__ . '/../../public/assets/js/common.js') ?: 1;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Pirata+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/tailwind.css?v=<?= e((string) $cssVersion) ?>">
</head>
<body>
    <div class="auth-layout">
        <!-- Painel esquerdo — imagem de fundo (50% da página) -->
        <div class="auth-hero">
            <div class="auth-hero-image"></div>
        </div>

        <!-- Painel direito — formulário -->
        <div class="auth-form-panel">
            <?php if (is_array($flash) && isset($flash['message'])): ?>
                <div class="flash flash-<?= e((string) $flash['type']) ?>">
                    <?= e((string) $flash['message']) ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>

    <script src="/assets/js/common.js?v=<?= e((string) $jsVersion) ?>"></script>
</body>
</html>
