<?php

/**
 * Página PÚBLICA do telão (GET /telao) — autônoma, sem sidebar/login.
 *
 * Exibe mapa em tempo real com:
 *   - Placar ao vivo (equipes Laranja e Preta)
 *   - Mapa Leaflet com posições das equipes em tempo real
 *   - Selfies das equipes
 *   - Relogio ao vivo e indicador AO VIVO
 *
 * @var string $siteName Nome do sistema (titulo)
 */

$siteName = $siteName ?? 'Caça ao Tesouro';

/**
 * Chave do CARTO (tiles do mapa). NÃO fica no repositório — vem do
 * data/install.php (arquivo local, ignorado pelo git) ou da variável de
 * ambiente CARTO_API_KEY. Sem chave o mapa usa o endereço antigo (sem chave).
 */
$cartoKey = trim((string) app_config('map.carto_key', ''));
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telão — <?= e($siteName) ?></title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

    <!-- Google Fonts: Pirata One + Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Pirata+One&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           TELAO — ESTILOS ESPECIFICOS
           ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #050B12;
            color: #f7ecd4;
            display: flex;
            flex-direction: column;
        }

        /* ---- HEADER ---- */
        .telao-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 28px;
            background: linear-gradient(90deg, #0A1724 0%, #0E1F30 50%, #0A1724 100%);
            border-bottom: 3px solid #F97316;
            flex-shrink: 0;
            z-index: 10;
        }

        .telao-title {
            font-family: 'Pirata One', Georgia, cursive;
            font-size: 1.5rem;
            color: #F97316;
            letter-spacing: 0.03em;
            text-shadow: 0 2px 8px rgba(249, 115, 22, 0.3);
        }

        .telao-header-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            animation: livePulse 1.5s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
            50%      { opacity: 0.7; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        }

        .telao-clock {
            font-family: 'Inter', monospace;
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(247, 236, 212, 0.6);
            min-width: 65px;
            text-align: right;
        }

        /* ---- MAIN BODY: 3 columns ---- */
        .telao-body {
            flex: 1;
            display: flex;
            min-height: 0;
        }

        /* Scoreboards */
        .scoreboard {
            width: 220px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #0A1724;
            border-right: 1px solid rgba(249, 115, 22, 0.1);
            overflow-y: auto;
        }

        .scoreboard:last-child {
            border-right: none;
            border-left: 1px solid rgba(249, 115, 22, 0.1);
        }

        .scoreboard-inner {
            padding: 20px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .sb-team-name {
            font-family: 'Pirata One', Georgia, cursive;
            font-size: 1.15rem;
            margin-bottom: 4px;
            text-align: center;
        }

        .sb-team-name.color-laranja { color: #F97316; }
        .sb-team-name.color-preta   { color: #94a3b8; }

        .sb-status {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: center;
            color: rgba(247, 236, 212, 0.45);
            margin-bottom: 12px;
        }

        .sb-status.sb-online {
            color: #22C55E;
            font-weight: 700;
        }

        .sb-status.sb-offline {
            color: #EF4444;
            font-weight: 700;
        }

        .sb-points {
            text-align: center;
            font-family: 'Pirata One', Georgia, cursive;
            font-size: 2.8rem;
            line-height: 1;
            margin-bottom: 4px;
        }

        .sb-points.color-laranja { color: #F97316; }
        .sb-points.color-preta   { color: #94a3b8; }

        .sb-label {
            text-align: center;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(247, 236, 212, 0.35);
            margin-bottom: 14px;
        }

        .sb-stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px solid rgba(247, 236, 212, 0.06);
            font-size: 0.78rem;
        }

        .sb-stat-label { color: rgba(247, 236, 212, 0.45); }
        .sb-stat-value { font-weight: 600; color: #f7ecd4; }

        /* Dispositivos conectados (clique centraliza no mapa) */
        .sb-devices {
            margin-top: 14px;
            width: 100%;
            border-top: 1px solid rgba(247, 236, 212, 0.08);
            padding-top: 10px;
        }
        .sb-devices-title {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(247, 236, 212, 0.45);
            margin-bottom: 8px;
        }
        .sb-devices-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 170px;
            overflow-y: auto;
        }
        .sb-device-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid rgba(247, 236, 212, 0.12);
            background: rgba(5, 11, 18, 0.5);
            color: #f7ecd4;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            transition: all .15s ease;
        }
        .sb-device-btn:hover {
            border-color: #F97316;
            background: rgba(249, 115, 22, 0.15);
            color: #F97316;
        }
        .sb-device-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .sb-device-btn.is-offline { opacity: 0.45; }
        .sb-device-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sb-devices-empty {
            font-size: 0.78rem;
            color: rgba(247, 236, 212, 0.35);
            font-style: italic;
        }

        /* Selfies area */
        .sb-selfies {
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid rgba(247, 236, 212, 0.08);
        }

        .sb-selfies-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(247, 236, 212, 0.35);
            margin-bottom: 8px;
            text-align: center;
        }

        .sb-selfies-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
        }

        .sb-selfie-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid rgba(249, 115, 22, 0.3);
            cursor: pointer;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .sb-selfie-thumb:hover {
            transform: scale(1.15);
            border-color: #F97316;
        }

        /* ---- MAP ---- */
        .telao-map-wrap {
            flex: 1;
            min-width: 0;
            position: relative;
        }

        #telao-map {
            width: 100%;
            height: 100%;
            /* Fundo claro enquanto os tiles carregam (o mapa é claro). */
            background: #FBF8F3;
        }

        /* ============================================================
           MAPA CLARO COM AS VIAS EM LARANJA
           O estilo Voyager já tem as vias principais em laranja/amarelo e
           texto cinza-escuro; o filtro só SATURA esse laranja.
           Cuidado: filtros com contrast()/brightness() num mapa claro
           estouram tudo para branco e o mapa some (era o bug anterior).
           ============================================================ */
        .leaflet-tile-pane {
            filter: saturate(2) hue-rotate(-14deg);
        }

        /* Controles do Leaflet legíveis sobre o mapa claro. */
        #telao-map .leaflet-control-zoom a {
            background: #FFFFFF;
            color: #B45309;
            border-color: rgba(180, 83, 9, 0.25);
            font-weight: 700;
        }

        #telao-map .leaflet-control-zoom a:hover {
            background: #FFF3E6;
            color: #F97316;
        }

        #telao-map .leaflet-control-attribution {
            background: rgba(255, 255, 255, 0.85);
            color: #4B5563;
        }

        #telao-map .leaflet-control-attribution a {
            color: #B45309;
        }

        .telao-map-error {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(5, 11, 18, 0.9);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f5a6a0;
            padding: 16px 28px;
            border-radius: 12px;
            font-size: 0.88rem;
            text-align: center;
            display: none;
            z-index: 500;
            backdrop-filter: blur(8px);
        }

        /* ---- MODAL (selfies) ---- */
        .telao-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .telao-modal-overlay.active {
            display: flex;
        }

        .telao-modal {
            position: relative;
            max-width: 520px;
            width: 90vw;
            background: #0E1F30;
            border: 1px solid rgba(249, 115, 22, 0.25);
            border-radius: 16px;
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.92); }
            to   { opacity: 1; transform: scale(1); }
        }

        .telao-modal-close {
            position: absolute;
            top: 12px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.5);
            color: #f7ecd4;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: background 0.15s;
        }

        .telao-modal-close:hover {
            background: rgba(192, 57, 43, 0.8);
        }

        .telao-modal-img {
            width: 100%;
            display: block;
        }

        .telao-modal-info {
            padding: 14px 18px;
        }

        .telao-modal-team {
            font-family: 'Pirata One', Georgia, cursive;
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .telao-modal-team.color-laranja { color: #F97316; }
        .telao-modal-team.color-preta   { color: #94a3b8; }

        .telao-modal-treasure {
            font-size: 0.85rem;
            color: rgba(247, 236, 212, 0.7);
        }

        .telao-modal-date {
            font-size: 0.75rem;
            color: rgba(247, 236, 212, 0.4);
            margin-top: 4px;
        }

        /* ---- RESPONSIVE ---- */
        @media (max-width: 900px) {
            .scoreboard { width: 170px; }
            .sb-points  { font-size: 2rem; }
        }

        @media (max-width: 640px) {
            .telao-body { flex-direction: column; }
            .scoreboard {
                width: 100%;
                flex-direction: row;
                border-right: none !important;
                border-left: none !important;
                border-bottom: 1px solid rgba(249, 115, 22, 0.1);
                overflow-x: auto;
            }
            .scoreboard-inner {
                flex-direction: row;
                align-items: center;
                gap: 16px;
                padding: 10px 14px;
            }
            .sb-points { font-size: 1.8rem; }
            .sb-selfies { display: none; }
        }

        /* ═══ PIN CARTUNESCO DAS EQUIPES (flat) ═══ */
        .team-pin {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px 16px 9px;
            border-radius: 12px;
            color: #fff;
            font-family: 'Comic Sans MS', 'Segoe UI', sans-serif;
            white-space: nowrap;
            line-height: 1;
            /* Contorno branco + sombra: o pino "salta" do mapa claro. */
            border: 3px solid #FFFFFF;
            box-shadow: 0 3px 10px rgba(10, 23, 36, 0.35);
        }
        .team-pin-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .team-pin-name {
            font-size: 16px;
            font-weight: 800;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .team-pin-tail {
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 9px solid transparent;
            border-right: 9px solid transparent;
            border-top: 12px solid;
        }
        .team-pin-laranja { background: #F97316; }
        .team-pin-laranja .team-pin-tail { border-top-color: #F97316; }
        .team-pin-preta { background: #111; }
        .team-pin-preta .team-pin-tail { border-top-color: #111; }
        @keyframes pin-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        /* ============================================================
           TELA DA EQUIPE VENCEDORA (aparece quando o jogo encerra)
           ============================================================ */
        .winner-overlay {
            position: fixed;
            inset: 0;
            z-index: 5000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: radial-gradient(circle at 50% 40%,
                rgba(249, 115, 22, 0.22), rgba(5, 11, 18, 0.97) 62%);
            backdrop-filter: blur(6px);
            text-align: center;
        }

        .winner-overlay.visible {
            display: flex;
            animation: winner-fade 0.5s ease-out;
        }

        @keyframes winner-fade {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .winner-box {
            max-width: 1100px;
            padding: 54px 72px;
            border-radius: 28px;
            border: 3px solid #F97316;
            box-shadow: 0 0 70px rgba(249, 115, 22, 0.45);
            animation: winner-pop 0.6s cubic-bezier(0.2, 1.5, 0.4, 1);
        }

        @keyframes winner-pop {
            from { transform: scale(0.75); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }

        .winner-trophy {
            font-size: 96px;
            line-height: 1;
            animation: winner-bounce 1.6s ease-in-out infinite;
        }

        @keyframes winner-bounce {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-14px); }
        }

        .winner-title {
            margin: 10px 0 4px;
            font-family: 'Pirata One', 'Comic Sans MS', serif;
            font-size: 44px;
            letter-spacing: 6px;
            color: #FDE68A;
            text-transform: uppercase;
        }

        .winner-caption {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: rgba(247, 236, 212, 0.65);
            margin-bottom: 18px;
        }

        .winner-name {
            font-family: 'Pirata One', 'Comic Sans MS', serif;
            font-size: 82px;
            line-height: 1.05;
            color: #FFFFFF;
            text-shadow: 0 4px 24px rgba(0, 0, 0, 0.55);
            margin-bottom: 18px;
        }

        .winner-points {
            display: inline-block;
            padding: 10px 26px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 2px solid rgba(255, 255, 255, 0.35);
            font-size: 26px;
            font-weight: 800;
            color: #FFFFFF;
        }

        .winner-close {
            position: absolute;
            top: 26px;
            right: 32px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid rgba(247, 236, 212, 0.35);
            background: rgba(5, 11, 18, 0.6);
            color: #F7ECD4;
            font-size: 22px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .winner-close:hover {
            background: rgba(249, 115, 22, 0.25);
            border-color: #F97316;
            color: #FFFFFF;
        }

        /* Confete caindo */
        .winner-confetti {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .winner-confetti i {
            position: absolute;
            top: -12vh;
            width: 12px;
            height: 20px;
            border-radius: 2px;
            opacity: 0.9;
            animation: confetti-fall linear infinite;
        }

        @keyframes confetti-fall {
            0%   { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(125vh) rotate(720deg); }
        }

        .leaflet-popup-content { color: #0a1724; }
    </style>
</head>
<body>

    <!-- ============================================================
         HEADER
         ============================================================ -->
    <header class="telao-header">
        <h1 class="telao-title">Ca&ccedil;a ao Tesouro &mdash; Tel&atilde;o</h1>
        <div class="telao-header-right">
            <span class="live-badge"><span class="live-dot"></span> AO VIVO</span>
            <span class="telao-clock" id="telao-clock">--:--:--</span>
        </div>
    </header>

    <!-- ============================================================
         BODY: 3 COLUMNS
         ============================================================ -->
    <div class="telao-body">

        <!-- PLACAR ESQUERDA (Laranja) -->
        <aside class="scoreboard" id="sb-laranja">
            <div class="scoreboard-inner">
                <div class="sb-team-name color-laranja" id="sb-laranja-name">Equipe Laranja</div>
                <div class="sb-status" id="sb-laranja-status">Aguardando...</div>
                <div class="sb-points color-laranja" id="sb-laranja-points">0</div>
                <div class="sb-label">Pontos</div>
                <div class="sb-stat-row">
                    <span class="sb-stat-label">Tesouros</span>
                    <span class="sb-stat-value" id="sb-laranja-found">0</span>
                </div>
                <div class="sb-stat-row">
                    <span class="sb-stat-label">Status</span>
                    <span class="sb-stat-value" id="sb-laranja-game-status">-</span>
                </div>
                <div class="sb-devices">
                    <div class="sb-devices-title">Dispositivos conectados</div>
                    <div class="sb-devices-list" id="sb-laranja-devices">
                        <div class="sb-devices-empty">Ninguém conectado</div>
                    </div>
                </div>
                <div class="sb-selfies" id="sb-laranja-selfies">
                    <div class="sb-selfies-title">Selfies</div>
                    <div class="sb-selfies-grid" id="sb-laranja-selfies-grid"></div>
                </div>
            </div>
        </aside>

        <!-- MAPA (centro) -->
        <div class="telao-map-wrap">
            <div id="telao-map"></div>
            <div class="telao-map-error" id="telao-error">Erro ao carregar dados. Tentando novamente...</div>
        </div>

        <!-- PLACAR DIREITA (Preta) -->
        <aside class="scoreboard" id="sb-preta">
            <div class="scoreboard-inner">
                <div class="sb-team-name color-preta" id="sb-preta-name">Equipe Preta</div>
                <div class="sb-status" id="sb-preta-status">Aguardando...</div>
                <div class="sb-points color-preta" id="sb-preta-points">0</div>
                <div class="sb-label">Pontos</div>
                <div class="sb-stat-row">
                    <span class="sb-stat-label">Tesouros</span>
                    <span class="sb-stat-value" id="sb-preta-found">0</span>
                </div>
                <div class="sb-stat-row">
                    <span class="sb-stat-label">Status</span>
                    <span class="sb-stat-value" id="sb-preta-game-status">-</span>
                </div>
                <div class="sb-devices">
                    <div class="sb-devices-title">Dispositivos conectados</div>
                    <div class="sb-devices-list" id="sb-preta-devices">
                        <div class="sb-devices-empty">Ninguém conectado</div>
                    </div>
                </div>
                <div class="sb-selfies" id="sb-preta-selfies">
                    <div class="sb-selfies-title">Selfies</div>
                    <div class="sb-selfies-grid" id="sb-preta-selfies-grid"></div>
                </div>
            </div>
        </aside>
    </div>

    <!-- ============================================================
         MODAL (selfie ampliada)
         ============================================================ -->
    <div class="telao-modal-overlay" id="telao-modal">
        <div class="telao-modal">
            <button class="telao-modal-close" id="telao-modal-close">&times;</button>
            <img class="telao-modal-img" id="telao-modal-img" src="" alt="Selfie">
            <div class="telao-modal-info">
                <div class="telao-modal-team" id="telao-modal-team"></div>
                <div class="telao-modal-treasure" id="telao-modal-treasure"></div>
                <div class="telao-modal-date" id="telao-modal-date"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         LEAFLET JS
         ============================================================ -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- ============================================================
         TELAO JS
         ============================================================ -->
    <script>
    (function () {
        'use strict';

        /* ---- CONFIG ---- */
        const COLORS = {
            laranja: '#F97316',
            preta:   '#2C3E50',
            pretaBorder: '#94a3b8',
        };

        /* ---- STATE ---- */
        let map = null;
        let teamMarkers = {};     // { 'laranja': L.circleMarker, 'preta': L.circleMarker }
        let firstLoad = true;

        /* ---- DOM REFS ---- */
        const $ = (sel) => document.querySelector(sel);

        /* ============================================================
           CLOCK
           ============================================================ */
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            $('#telao-clock').textContent = h + ':' + m + ':' + s;
        }
        setInterval(updateClock, 1000);
        updateClock();

        /* ============================================================
           MAP INIT
           ============================================================ */
        function initMap() {
            map = L.map('telao-map', {
                center: [-22.01392755007763, -47.42434367236048],
                zoom: 15,
                zoomControl: true,
                attributionControl: true,
            });

            /* CARTO: estilo Voyager (claro, vias em laranja) + chave da API
               quando configurada.
               A chave fica em data/install.php (fora do git) ou na env CARTO_API_KEY. */
            const CARTO_KEY = <?= json_encode($cartoKey, JSON_UNESCAPED_SLASHES) ?>;

            /* Voyager: fundo claro, vias principais em laranja e texto
               cinza-escuro — é o estilo que dá o contraste laranja/cinza. */
            const cartoTileUrl = CARTO_KEY
                ? 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png'
                    + '?key=' + encodeURIComponent(CARTO_KEY)
                : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png';

            L.tileLayer(cartoTileUrl, {
                maxZoom: 19,
                subdomains: 'abcd',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    + ' &copy; <a href="https://carto.com/attributions">CARTO</a>',
            }).addTo(map);
        }

        /* ============================================================
           LOAD DATA
           ============================================================ */
        async function loadTelao() {
            const errorEl = $('#telao-error');
            try {
                const res = await fetch('/api/telao?t=' + Date.now(), { cache: 'no-store' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                if (!data.success) throw new Error('API returned success=false');

                errorEl.style.display = 'none';
                renderTeams(data.teams || []);
                renderDevices(data.devices || []);
                renderDevicesList(data.devices || []);
                renderSelfies(data.selfies || []);
                renderWinner(data.winner || null);

                /* Fit bounds on first load */
                if (firstLoad) {
                    fitMapBounds(data.devices || []);
                    firstLoad = false;
                }
            } catch (err) {
                console.error('[Telao] fetch error:', err);
                errorEl.style.display = 'block';
            }
        }

        /* ============================================================
           FIT MAP BOUNDS
           ============================================================ */
        function fitMapBounds(devices) {
            const pts = [];
            devices.forEach(d => {
                if (d.online && d.lat !== null && d.lng !== null) {
                    pts.push([d.lat, d.lng]);
                }
            });
            if (pts.length > 0) {
                map.fitBounds(pts, { padding: [50, 50], maxZoom: 16 });
            }
        }

        /* ============================================================
           RENDER TEAMS (scoreboard + map markers)
           ============================================================ */
        function renderTeams(teams) {
            teams.forEach(team => {
                const colorKey = (team.color || '').toLowerCase();

                /* ---- Scoreboard ---- */
                const side = (colorKey === 'laranja') ? 'laranja' : 'preta';
                const prefix = '#sb-' + side;

                $(prefix + '-name').textContent = team.name || ('Equipe ' + side);
                $(prefix + '-points').textContent = team.points || 0;
                $(prefix + '-found').textContent = team.found_count || 0;

                const statusText = team.status === 'finished' ? 'Terminou' : 'Jogando';
                $(prefix + '-status').textContent = team.online ? '● ONLINE' : '○ OFFLINE';
                $(prefix + '-status').className = 'sb-status ' + (team.online ? 'sb-online' : 'sb-offline');
                $(prefix + '-game-status').textContent = statusText;
            });
        }

        /* ============================================================
           RENDER DEVICES (um pino por APARELHO, só com o NOME)
           ============================================================ */
        function renderDevices(devices) {
            const seen = {};

            devices.forEach(dev => {
                const colorKey = (dev.color || '').toLowerCase();
                const key = dev.device_id || (colorKey + '|' + dev.name);

                if (!dev.online || dev.lat === null || dev.lng === null) {
                    if (teamMarkers[key]) {
                        map.removeLayer(teamMarkers[key]);
                        delete teamMarkers[key];
                    }
                    return;
                }

                seen[key] = true;

                /* Pino com a cor da equipe e SÓ o nome do aparelho */
                const icon = L.divIcon({
                    className: 'team-pin-wrap',
                    html: '<div class="team-pin team-pin-' + colorKey + '">' +
                        '<span class="team-pin-name">' + escapeHtmlTelao(dev.name || 'Sem nome') + '</span>' +
                        '<div class="team-pin-tail"></div>' +
                        '</div>',
                    iconSize: [160, 44],
                    iconAnchor: [80, 44],
                    popupAnchor: [0, -40],
                });

                if (teamMarkers[key]) {
                    teamMarkers[key].setLatLng([dev.lat, dev.lng]);
                    teamMarkers[key].setIcon(icon);
                } else {
                    teamMarkers[key] = L.marker([dev.lat, dev.lng], { icon }).addTo(map);
                }

                teamMarkers[key].bindPopup(
                    '<strong>' + escapeHtmlTelao(dev.name || 'Sem nome') + '</strong><br>' +
                    'Equipe: ' + escapeHtmlTelao(dev.team || colorKey) + '<br>● ONLINE',
                    { className: 'telao-popup' }
                );
            });

            /* Remove pinos de aparelhos que saíram */
            Object.keys(teamMarkers).forEach(k => {
                if (!seen[k]) {
                    map.removeLayer(teamMarkers[k]);
                    delete teamMarkers[k];
                }
            });
        }

        /** Escapa texto que vai para o HTML do pino (o nome vem do celular). */
        function escapeHtmlTelao(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        /* ============================================================
           LISTA DE DISPOSITIVOS (abaixo do status de cada equipe)
           Clique no nome -> centraliza a posição dele no mapa
           ============================================================ */
        function renderDevicesList(devices) {
            const lists = {
                laranja: $('#sb-laranja-devices'),
                preta:   $('#sb-preta-devices'),
            };

            const cores = { laranja: '#F97316', preta: '#94a3b8' };

            // Agrupa por equipe — só aparelhos CONECTADOS (online).
            // Quem saiu do jogo (ou está sem sinal) não aparece na lista.
            const porEquipe = { laranja: [], preta: [] };

            (devices || []).forEach(dev => {
                const key = (dev.color || '').toLowerCase();
                if (!porEquipe[key]) return;
                if (!dev.online) return;

                porEquipe[key].push(dev);
            });

            Object.keys(lists).forEach(key => {
                const el = lists[key];
                if (!el) return;

                el.innerHTML = '';

                const lista = porEquipe[key];

                if (!lista || lista.length === 0) {
                    el.innerHTML = '<div class="sb-devices-empty">Ninguém conectado</div>';
                    return;
                }

                lista.forEach(dev => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'sb-device-btn';
                    btn.title = 'Clique para centralizar no mapa';

                    const dot = document.createElement('span');
                    dot.className = 'sb-device-dot';
                    dot.style.background = cores[key] || '#F97316';

                    const nome = document.createElement('span');
                    nome.className = 'sb-device-name';
                    nome.textContent = dev.name || 'Sem nome';

                    btn.appendChild(dot);
                    btn.appendChild(nome);

                    // Clicou: centraliza o mapa na posição do aparelho
                    btn.addEventListener('click', () => {
                        const marker = teamMarkers[dev.device_id];

                        if (dev.lat === null || dev.lng === null || !dev.online) {
                            return;
                        }

                        map.setView([dev.lat, dev.lng], 18, { animate: true });

                        if (marker) {
                            setTimeout(() => marker.openPopup(), 350);
                        }
                    });

                    el.appendChild(btn);
                });
            });
        }

        /* ============================================================
           RENDER TREASURES (map markers)
           ============================================================ */
        /* ============================================================
           RENDER SELFIES
           ============================================================ */
        function renderSelfies(selfies) {
            const grids = {
                laranja: $('#sb-laranja-selfies-grid'),
                preta:   $('#sb-preta-selfies-grid'),
            };

            /* Clear previous */
            grids.laranja.innerHTML = '';
            grids.preta.innerHTML = '';

            selfies.forEach(selfie => {
                const colorKey = (selfie.team_color || '').toLowerCase();
                const grid = grids[colorKey] || grids.preta;

                const img = document.createElement('img');
                img.className = 'sb-selfie-thumb';
                img.src = selfie.image_path;
                img.alt = selfie.team_name + ' — ' + selfie.treasure_name;
                img.title = selfie.team_name + ' — ' + selfie.treasure_name;

                img.addEventListener('click', function () {
                    openSelfieModal(selfie);
                });

                grid.appendChild(img);
            });
        }

        /* ============================================================
           SELFIE MODAL
           ============================================================ */
        function openSelfieModal(selfie) {
            const colorKey = (selfie.team_color || '').toLowerCase();
            $('#telao-modal-img').src = selfie.image_path;
            $('#telao-modal-team').textContent = selfie.team_name || '';
            $('#telao-modal-team').className = 'telao-modal-team color-' + colorKey;
            $('#telao-modal-treasure').textContent = 'Tesouro: ' + (selfie.treasure_name || '');

            if (selfie.found_at) {
                const d = new Date(selfie.found_at);
                $('#telao-modal-date').textContent = d.toLocaleString('pt-BR');
            } else {
                $('#telao-modal-date').textContent = '';
            }

            $('#telao-modal').classList.add('active');
        }

        function closeSelfieModal() {
            $('#telao-modal').classList.remove('active');
        }

        /* Modal close handlers */
        $('#telao-modal-close').addEventListener('click', closeSelfieModal);
        $('#telao-modal').addEventListener('click', function (e) {
            if (e.target === this) closeSelfieModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSelfieModal();
        });

        /* ============================================================
           TELA DA EQUIPE VENCEDORA
           Aparece quando alguém acerta o desafio final (o jogo encerra).
           O operador pode fechar no ✕ / Esc — e ela volta se o vencedor mudar.
           ============================================================ */
        let winnerShownId = null;
        let winnerDismissedId = null;

        const CORES_EQUIPE = {
            laranja: ['#F97316', '#B45309'],
            preta:   ['#1F2937', '#0B0F14'],
        };

        function buildConfetti(box) {
            const conf = document.getElementById('winner-confetti');
            const cores = ['#F97316', '#FDE68A', '#FFFFFF', '#F59E0B'];
            let html = '';

            for (let i = 0; i < 36; i++) {
                const left = (i * 2.7 + (i % 5) * 3) % 100;
                const delay = ((i % 9) * 0.45).toFixed(2);
                const dur = (4.5 + (i % 6) * 0.6).toFixed(2);
                const cor = cores[i % cores.length];
                const w = 8 + (i % 4) * 3;

                html += '<i style="left:' + left + '%;background:' + cor
                    + ';animation-duration:' + dur + 's;animation-delay:' + delay
                    + 's;width:' + w + 'px"></i>';
            }

            conf.innerHTML = html;
        }

        function renderWinner(winner) {
            const overlay = document.getElementById('winner-overlay');

            // Sem vencedor (jogo rolando): garante que a tela está escondida.
            if (!winner) {
                overlay.classList.remove('visible');
                winnerShownId = null;
                winnerDismissedId = null;
                return;
            }

            // O operador fechou a tela deste vencedor: não reabre sozinha.
            if (winnerDismissedId !== null && winnerDismissedId === winner.id) {
                return;
            }

            const box = document.getElementById('winner-box');
            const cores = CORES_EQUIPE[(winner.color || '').toLowerCase()]
                || ['#334155', '#0B0F14'];

            box.style.background = 'linear-gradient(135deg, '
                + cores[0] + ' 0%, ' + cores[1] + ' 100%)';

            document.getElementById('winner-name').textContent = winner.name || 'Equipe';
            document.getElementById('winner-points').textContent =
                (winner.points || 0) + ' pontos';

            // Só anima/toca quando o vencedor MUDA (não a cada 5s).
            if (winnerShownId !== winner.id) {
                winnerShownId = winner.id;
                buildConfetti(box);
                tocarRisada();
            }

            overlay.classList.add('visible');
        }

        /* ============================================================
           SOM DA VITÓRIA (risada — a mesma do aplicativo)
           O navegador só deixa tocar áudio depois de alguma interação do
           usuário. Tentamos na hora e, se for bloqueado, toca no primeiro
           clique/tecla/toque do operador.
           ============================================================ */
        let somBloqueado = false;

        function tocarRisada() {
            const audio = document.getElementById('winner-sound');
            if (!audio) return;

            audio.currentTime = 0;

            const tentar = audio.play();

            if (tentar && typeof tentar.catch === 'function') {
                tentar
                    .then(() => { somBloqueado = false; })
                    .catch(() => {
                        // Autoplay bloqueado: toca na primeira interação.
                        somBloqueado = true;
                    });
            }
        }

        ['click', 'keydown', 'touchstart'].forEach(function (evento) {
            document.addEventListener(evento, function () {
                if (somBloqueado) {
                    somBloqueado = false;
                    tocarRisada();
                }
            }, { once: false, passive: true });
        });

        document.getElementById('winner-close').addEventListener('click', function () {
            winnerDismissedId = winnerShownId;
            document.getElementById('winner-overlay').classList.remove('visible');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                winnerDismissedId = winnerShownId;
                document.getElementById('winner-overlay').classList.remove('visible');
            }
        });

        /* ============================================================
           INIT
           ============================================================ */
        initMap();
        loadTelao();
        setInterval(loadTelao, 5000);

    })();
    </script>
    <!-- ============================================================
         TELA DA EQUIPE VENCEDORA (fim de jogo)
         ============================================================ -->
    <!-- Risada da vitória: MESMO áudio que o aplicativo toca ao acertar a
         senha do desafio final. -->
    <audio id="winner-sound" src="/assets/sons/risada.mp3" preload="auto"></audio>

    <div class="winner-overlay" id="winner-overlay">
        <div class="winner-confetti" id="winner-confetti"></div>

        <button type="button" class="winner-close" id="winner-close"
                title="Voltar ao placar (Esc)">✕</button>

        <div class="winner-box" id="winner-box">
            <div class="winner-trophy">🏆</div>
            <div class="winner-title">Fim de Jogo</div>
            <div class="winner-caption">Equipe vencedora</div>
            <div class="winner-name" id="winner-name">—</div>
            <div class="winner-points" id="winner-points">0 pontos</div>
        </div>
    </div>

    </body>
</html>

