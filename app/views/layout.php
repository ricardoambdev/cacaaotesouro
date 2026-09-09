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
    <meta name="csrf-token-data" content="<?= e(csrf_token()) ?>">
    <style>
        /* ═══ CHAT FLUTUANTE ═══ */
        .chat-fab {
            position: fixed; right: 22px; bottom: 22px; z-index: 900;
            width: 58px; height: 58px; border-radius: 50%; border: none; cursor: pointer;
            background: linear-gradient(135deg, #F97316, #EA580C);
            color: #fff; font-size: 24px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 18px rgba(234, 88, 12, 0.45); transition: transform .15s;
        }
        .chat-fab:hover { transform: scale(1.08); }
        .chat-panel {
            position: fixed; right: 22px; bottom: 92px; z-index: 950;
            width: 340px; max-width: calc(100vw - 32px); height: 460px; max-height: calc(100vh - 130px);
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
        @media (max-width: 600px) { .chat-panel { width: calc(100vw - 32px); right: 16px; } }
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

    <!-- ═══ CHAT FLUTUANTE (mensagens para as equipes) ═══ -->
    <button id="chat-fab" class="chat-fab" aria-label="Abrir chat">💬</button>

    <div id="chat-panel" class="chat-panel" style="display:none;">
        <div class="chat-header">📣 Mensagens para as equipes</div>
        <div class="chat-recipients">
            <button type="button" class="chat-recipient active" data-target="todos">Todos</button>
            <button type="button" class="chat-recipient" data-target="laranja">🟠 Equipe Laranja</button>
            <button type="button" class="chat-recipient" data-target="preta">⚫ Equipe Preta</button>
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
        var fab = document.getElementById('chat-fab');
        var panel = document.getElementById('chat-panel');
        var historyEl = document.getElementById('chat-history');
        var input = document.getElementById('chat-input');
        var sendBtn = document.getElementById('chat-send');
        var meta = document.querySelector('meta[name="csrf-token-data"]');
        var csrf = meta ? meta.getAttribute('content') : '';
        var target = 'todos';
        if (!fab || !panel) return;

        fab.addEventListener('click', function () {
            var isOpen = panel.style.display !== 'none';
            panel.style.display = isOpen ? 'none' : 'flex';
            if (!isOpen) loadHistory();
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
            }).catch(function () { alert('Erro ao enviar.'); })
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
</body>
</html>
