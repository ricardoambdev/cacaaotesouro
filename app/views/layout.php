<?php

/**
 * Layout autenticado (painel, configurações).
 *
 * @var string                 $siteName Nome do site (título)
 * @var array{name:string}     $user     Usuário logado (usado na topbar)
 * @var string                 $active   Página ativa: 'painel' | 'configuracoes'
 * @var array{type:string,message:string}|null $flash Mensagem flash (opcional)
 * @var string                 $content  HTML renderizado da view interna
 */

$siteName = $siteName ?? 'Caça ao Tesouro';
$user = $user ?? [];
$active = $active ?? '';
$flash = $flash ?? null;
$userName = (string) ($user['name'] ?? '');

// Cache-busting: versão automática pelo mtime do arquivo
$cssVersion = @filemtime(__DIR__ . '/../../public/assets/css/tailwind.css') ?: 1;
$jsVersion = @filemtime(__DIR__ . '/../../public/assets/js/common.js') ?: 1;

// Gerar iniciais do nome
$parts = explode(' ', trim($userName));
$initials = '';
if (count($parts) >= 2) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1)) . mb_strtoupper(mb_substr(end($parts), 0, 1));
} elseif ($userName !== '') {
    $initials = mb_strtoupper(mb_substr($userName, 0, 1));
}
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
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay"></div>

    <!-- Hamburger mobile -->
    <button class="hamburger" aria-label="Abrir menu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>

    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/" class="sidebar-logo">
                    <!-- Bússola SVG -->
                    <svg class="sidebar-logo-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="18" stroke="#F97316" stroke-width="2" opacity="0.4"/>
                        <circle cx="20" cy="20" r="14" stroke="#F97316" stroke-width="1" opacity="0.2"/>
                        <circle cx="20" cy="20" r="3" fill="#F97316"/>
                        <polygon points="20,5 22,18 20,20 18,18" fill="#c0392b" opacity="0.9"/>
                        <polygon points="20,35 22,22 20,20 18,22" fill="#f7ecd4" opacity="0.7"/>
                        <polygon points="5,20 18,18 20,20 18,22" fill="#f7ecd4" opacity="0.5"/>
                        <polygon points="35,20 22,18 20,20 22,22" fill="#f7ecd4" opacity="0.5"/>
                    </svg>
                    <span class="sidebar-logo-text">Caça ao Tesouro</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="/" class="sidebar-link <?= $active === 'painel' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Painel
                </a>

                <a href="/tesouros" class="sidebar-link <?= $active === 'tesouros' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/>
                        <path d="M4 19l5-5"/>
                        <path d="M20 19l-5-5"/>
                        <line x1="9" y1="8" x2="9" y2="8.01"/>
                        <line x1="15" y1="8" x2="15" y2="8.01"/>
                        <line x1="9" y1="14" x2="15" y2="14"/>
                        <line x1="12" y1="11" x2="12" y2="17"/>
                    </svg>
                    Tesouros
                </a>

                <a href="/jogo" class="sidebar-link <?= $active === 'jogo' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Jogo
                </a>

                <a href="/historia" class="sidebar-link <?= $active === 'historia' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/>
                        <path d="M4 19l5-5"/>
                        <path d="M20 19l-5-5"/>
                        <line x1="9" y1="8" x2="15" y2="8"/>
                    </svg>
                    História
                </a>

                <a href="/desafio-final" class="sidebar-link <?= $active === 'desafio-final' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Desafio Final
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/configuracoes" class="sidebar-link <?= $active === 'configuracoes' ? 'active' : '' ?>">
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Configurações
                </a>

                <form method="post" action="/logout" class="sidebar-logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="sidebar-logout-btn">
                        <svg class="sidebar-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Sair
                    </button>
                </form>
            </div>
        </aside>

        <!-- Conteúdo principal -->
        <main class="main">
            <header class="topbar">
                <span class="topbar-name"><?= e($userName) ?></span>
                <div class="topbar-avatar"><?= e($initials) ?></div>
            </header>

            <?php if (is_array($flash) && isset($flash['message'])): ?>
                <div class="flash flash-<?= e((string) $flash['type']) ?>">
                    <?= e((string) $flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="content">
                <?= $content ?>
            </div>
        </main>
    </div>

    <script src="/assets/js/common.js?v=<?= e((string) $jsVersion) ?>"></script>
</body>
</html>
