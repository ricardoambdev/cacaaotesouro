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
    <link rel="stylesheet" href="/assets/css/icons.css?v=<?= e((string) @filemtime(__DIR__ . '/../../public/assets/css/icons.css')) ?>">
    <meta name="csrf-token-data" content="<?= e(csrf_token()) ?>">
    <style>
        /* ═══ CHAT NO HEADER ═══ */
        .topbar-chat {
            position: relative;
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; flex-shrink: 0;
            border-radius: 50%; border: 1.5px solid rgba(249,115,22,0.3);
            background: rgba(249,115,22,0.1); color: #f7ecd4;
            font-size: 17px; cursor: pointer; transition: all .15s;
        }
        .topbar-chat:hover { background: rgba(249,115,22,0.22); border-color: #F97316; }
        .topbar-chat.active { background: rgba(249,115,22,0.28); border-color: #F97316; }
        .chat-panel {
            position: fixed; top: 72px; right: 24px; z-index: 950;
            width: 340px; max-width: calc(100vw - 32px); height: 460px; max-height: calc(100vh - 100px);
            background: #0A1724; border: 1px solid rgba(249,115,22,0.25); border-radius: 16px;
            display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,.55); animation: chatIn .2s ease;
        }
        @keyframes chatIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .chat-header {
            padding: 14px 16px; background: rgba(249,115,22,0.1);
            border-bottom: 1px solid rgba(249,115,22,0.15);
            font-weight: 700; color: #f7ecd4; font-size: .95rem;
        }
        .chat-recipients { display: flex; gap: 8px; padding: 10px 14px; border-bottom: 1px solid rgba(249,115,22,0.1); }
        .chat-recipient {
            flex: 1; padding: 7px 6px; border-radius: 8px; border: 1px solid rgba(249,115,22,0.25);
            background: transparent; color: rgba(247,236,212,0.7); font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .15s;
        }
        .chat-recipient.active { background: rgba(249,115,22,0.18); border-color: #F97316; color: #F97316; }
        .chat-history { flex: 1; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
        .chat-msg {
            max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: .82rem; line-height: 1.45;
            background: rgba(249,115,22,0.12); border: 1px solid rgba(249,115,22,0.15); color: #f7ecd4; align-self: flex-end;
        }
        .chat-msg .chat-msg-who { display: block; font-size: .68rem; color: #F97316; font-weight: 700; margin-bottom: 3px; }
        .chat-empty { color: rgba(247,236,212,0.4); font-size: .8rem; text-align: center; padding: 20px 10px; }
        .chat-input-row { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid rgba(249,115,22,0.12); }
        .chat-input-row input {
            flex: 1; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(249,115,22,0.25);
            background: rgba(5,11,18,0.7); color: #f7ecd4; font-size: .85rem; outline: none; min-width: 0;
        }
        .chat-input-row input:focus { border-color: #F97316; }
        .chat-input-row button {
            padding: 10px 16px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; font-size: .82rem;
            background: linear-gradient(135deg, #F97316, #EA580C); color: #fff;
        }
        @media (max-width: 600px) { .chat-panel { width: calc(100vw - 32px); right: 16px; top: 68px; } }

        /* ═══ MODAL DE CONFIRMAÇÃO DO SISTEMA ═══ */
        .sysmodal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 10050;
            background: rgba(0, 0, 0, 0.82);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .sysmodal.open { display: flex; }
        .sysmodal-card {
            background: #0A1724;
            border: 1px solid rgba(249, 115, 22, 0.3);
            border-radius: 16px;
            max-width: 440px;
            width: 100%;
            padding: 26px 24px 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            text-align: center;
            animation: sysmodalIn .18s ease;
        }
        @keyframes sysmodalIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        .sysmodal-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }
        .sysmodal-msg {
            color: #f7ecd4;
            font-size: .95rem;
            line-height: 1.6;
            margin-bottom: 22px;
            white-space: pre-line;
        }
        .sysmodal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .sysmodal-btn {
            padding: 11px 22px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            font-size: .88rem;
            cursor: pointer;
            transition: transform .12s;
        }
        .sysmodal-btn:hover { transform: translateY(-1px); }
        .sysmodal-btn.cancel {
            background: rgba(247, 236, 212, 0.08);
            color: #f7ecd4;
            border: 1px solid rgba(247, 236, 212, 0.18);
        }
        .sysmodal-btn.confirm {
            background: linear-gradient(135deg, #F97316, #EA580C);
            color: #fff;
        }
        .sysmodal-btn.confirm.danger {
            background: linear-gradient(135deg, #c0392b, #8e2a20);
        }
    </style>
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
                    <?= icon('dashboard', 20, 'sidebar-link-icon') ?>
                    Painel
                </a>

                <a href="/tesouros" class="sidebar-link <?= $active === 'tesouros' ? 'active' : '' ?>">
                    <?= icon('map', 20, 'sidebar-link-icon') ?>
                    Tesouros
                </a>

                <a href="/jogo" class="sidebar-link <?= $active === 'jogo' ? 'active' : '' ?>">
                    <?= icon('check_circle', 20, 'sidebar-link-icon') ?>
                    Jogo
                </a>

                <a href="/historia" class="sidebar-link <?= $active === 'historia' ? 'active' : '' ?>">
                    <?= icon('menu_book', 20, 'sidebar-link-icon') ?>
                    História
                </a>

                <a href="/desafio-final" class="sidebar-link <?= $active === 'desafio-final' ? 'active' : '' ?>">
                    <?= icon('emoji_events', 20, 'sidebar-link-icon') ?>
                    Desafio Final
                </a>

                <a href="/cofre/config" class="sidebar-link <?= $active === 'cofre' ? 'active' : '' ?>">
                    <?= icon('lock', 20, 'sidebar-link-icon') ?>
                    Cofre
                </a>
                <a href="/regras" class="sidebar-link <?= $active === 'regras' ? 'active' : '' ?>">
                    <?= icon('gavel', 20, 'sidebar-link-icon') ?>
                    Regras
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/configuracoes" class="sidebar-link <?= $active === 'configuracoes' ? 'active' : '' ?>">
                    <?= icon('settings', 20, 'sidebar-link-icon') ?>
                    Configurações
                </a>

                <form method="post" action="/logout" class="sidebar-logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="sidebar-logout-btn">
                        <?= icon('logout', 20, 'sidebar-logout-icon') ?>
                        Sair
                    </button>
                </form>
            </div>
        </aside>

        <!-- Conteúdo principal -->
        <main class="main">
            <?php $statusTag = game_status_tag(); ?>
            <header class="topbar">
                <span class="badge <?= e($statusTag['class']) ?> topbar-status" title="Status da partida">
                    <?= icon((string) $statusTag['icon'], 16) ?> <?= e($statusTag['label']) ?>
                </span>
                <button id="chat-toggle" class="topbar-chat" type="button" aria-label="Mensagens para as equipes" title="Mensagens para as equipes">
                    <?= icon('chat', 20) ?>
                </button>
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

    <!-- ═══ CHAT (mensagens para as equipes) ═══ -->
    <div id="chat-panel" class="chat-panel" style="display:none;">
        <div class="chat-header"><?= icon('campaign', 18) ?> Mensagens para as equipes</div>
        <div class="chat-recipients">
            <button type="button" class="chat-recipient active" data-target="todos">Todos</button>
            <button type="button" class="chat-recipient" data-target="laranja"><?= icon('circle', 12, 'chat-recipient-dot', 'style="color:#E67E22;"') ?> Equipe Laranja</button>
            <button type="button" class="chat-recipient" data-target="preta"><?= icon('circle', 12, 'chat-recipient-dot', 'style="color:#95A5A6;"') ?> Equipe Preta</button>
        </div>
        <div class="chat-history" id="chat-history">
            <div class="chat-empty">Carregando mensagens...</div>
        </div>
        <div class="chat-input-row">
            <input type="text" id="chat-input" maxlength="500" placeholder="Digite a mensagem..." autocomplete="off">
            <button type="button" id="chat-send">Enviar</button>
        </div>
    </div>

    <script>
    (function () {
        var fab = document.getElementById('chat-toggle');
        var panel = document.getElementById('chat-panel');
        var historyEl = document.getElementById('chat-history');
        var input = document.getElementById('chat-input');
        var sendBtn = document.getElementById('chat-send');
        var meta = document.querySelector('meta[name="csrf-token-data"]');
        var csrf = meta ? meta.getAttribute('content') : '';
        var target = 'todos';
        if (!fab || !panel) return;

        function openChat() {
            panel.style.display = 'flex';
            fab.classList.add('active');
            loadHistory();
        }
        function closeChat() {
            panel.style.display = 'none';
            fab.classList.remove('active');
        }

        fab.addEventListener('click', function () {
            var isOpen = panel.style.display !== 'none';
            if (isOpen) { closeChat(); } else { openChat(); }
        });

        /* Fechar ao clicar fora */
        document.addEventListener('click', function (e) {
            if (panel.style.display === 'none') return;
            if (panel.contains(e.target) || fab.contains(e.target)) return;
            closeChat();
        });

        /* Fechar com Esc */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.style.display !== 'none') {
                closeChat();
            }
        });

        document.querySelectorAll('.chat-recipient').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.chat-recipient').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                target = btn.getAttribute('data-target');
            });
        });

        function loadHistory() {
            fetch('/admin/mensagens', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    historyEl.innerHTML = '';
                    if (!data.messages || data.messages.length === 0) {
                        historyEl.innerHTML = '<div class="chat-empty">Nenhuma mensagem enviada ainda.</div>';
                        return;
                    }
                    data.messages.slice(-20).forEach(function (m) {
                        var div = document.createElement('div');
                        div.className = 'chat-msg';
                        var who = m.team_id === 0 ? 'Todos' : (m.team_name || 'Equipe');
                        div.innerHTML = '<span class="chat-msg-who">' + who + '</span>' +
                            escapeHtml(m.message);
                        historyEl.appendChild(div);
                    });
                    historyEl.scrollTop = historyEl.scrollHeight;
                })
                .catch(function () {
                    historyEl.innerHTML = '<div class="chat-empty">Não foi possível carregar.</div>';
                });
        }

        function sendMessage() {
            var message = input.value.trim();
            if (!message) return;
            sendBtn.disabled = true;
            fetch('/admin/mensagem', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrf
                },
                body: 'target=' + encodeURIComponent(target) + '&message=' + encodeURIComponent(message)
            }).then(function () {
                input.value = '';
                loadHistory();
            }).catch(function () {
                if (window.showSystemMessage) { window.showSystemMessage('Erro ao enviar a mensagem.', 'error'); }
            })
              .finally(function () { sendBtn.disabled = false; });
        }

        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendMessage(); });

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
    })();
    </script>
<!-- ═══ MODAL DE CONFIRMAÇÃO (substitui o confirm() do navegador) ═══ -->
<div id="sysmodal" class="sysmodal" role="dialog" aria-modal="true">
    <div class="sysmodal-card">
        <div class="sysmodal-icon" id="sysmodal-icon"><?= icon('warning', 32) ?></div>
        <div class="sysmodal-msg" id="sysmodal-msg"></div>
        <div class="sysmodal-actions">
            <button type="button" class="sysmodal-btn cancel" id="sysmodal-cancel">Cancelar</button>
            <button type="button" class="sysmodal-btn confirm" id="sysmodal-confirm">Confirmar</button>
        </div>
    </div>
</div>
</body>
</html>
