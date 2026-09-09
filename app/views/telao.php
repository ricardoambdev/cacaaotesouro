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

        /* ═══ PIN CARTUNESCO DAS EQUIPES ═══ */
        .team-pin-wrap {
            filter: drop-shadow(0 5px 8px rgba(0, 0, 0, 0.45));
        }
        .team-pin {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5px 16px 9px;
            background: linear-gradient(180deg, #fffdf6 0%, #fdf3dd 100%);
            border: 3px solid;
            border-radius: 16px 16px 16px 16px;
            box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.08);
            font-family: 'Comic Sans MS', 'Segoe UI', sans-serif;
            white-space: nowrap;
            line-height: 1;
            animation: pin-bounce 1.4s ease-in-out infinite;
        }
        .team-pin-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #8b8378;
            margin-bottom: 4px;
        }
        .team-pin-name {
            font-size: 16px;
            font-weight: 900;
        }
        .team-pin-tail {
            position: absolute;
            bottom: -13px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 9px solid transparent;
            border-right: 9px solid transparent;
            border-top: 13px solid currentColor;
        }
        .team-pin-preta { color: #111; border-color: #111; }
        .team-pin-preta .team-pin-name { color: #000; }
        .team-pin-laranja { color: #F97316; border-color: #F97316; }
        .team-pin-laranja .team-pin-name { color: #F97316; }
        @keyframes pin-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
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

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
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
                renderSelfies(data.selfies || []);

                /* Fit bounds on first load */
                if (firstLoad) {
                    fitMapBounds(data.teams || []);
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
        function fitMapBounds(teams) {
            const pts = [];
            teams.forEach(t => {
                if (t.last_location) pts.push([t.last_location.lat, t.last_location.lng]);
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

                /* ---- Map marker: somente se ONLINE (logada no app) ---- */
                if (team.online && team.last_location) {
                    const lat = team.last_location.lat;
                    const lng = team.last_location.lng;
                    const teamColor = (colorKey === 'laranja') ? COLORS.laranja : COLORS.preta;

                    /* Pin cartunesco com o nome da equipe */
                    const colorName = (colorKey === 'laranja') ? 'Laranja' : 'Preta';
                    const icon = L.divIcon({
                        className: 'team-pin-wrap',
                        html: '<div class="team-pin team-pin-' + colorKey + '">' +
                            '<span class="team-pin-label">Equipe</span>' +
                            '<span class="team-pin-name">' + colorName + '</span>' +
                            '<div class="team-pin-tail"></div>' +
                            '</div>',
                        iconSize: [130, 54],
                        iconAnchor: [65, 54],
                        popupAnchor: [0, -50],
                    });

                    if (teamMarkers[colorKey]) {
                        /* Update existing */
                        teamMarkers[colorKey].setLatLng([lat, lng]);
                    } else {
                        /* Create new */
                        teamMarkers[colorKey] = L.marker([lat, lng], { icon }).addTo(map);
                    }

                    teamMarkers[colorKey].bindPopup(
                        '<strong>' + (team.name || side) + '</strong><br>Pontos: ' + (team.points || 0) +
                            '<br>● ONLINE',
                        { className: 'telao-popup' }
                    );
                } else if (teamMarkers[colorKey]) {
                    /* Offline ou sem localização — NÃO mostra no mapa */
                    map.removeLayer(teamMarkers[colorKey]);
                    delete teamMarkers[colorKey];
                }
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
           INIT
           ============================================================ */
        initMap();
        loadTelao();
        setInterval(loadTelao, 5000);

    })();
    </script>
</body>
</html>
