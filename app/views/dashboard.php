<?php

/**
 * Painel de gestão — mapa em tempo real + sidebar de equipes.
 *
 * @var array{id:int,name:string,email:string}  $user        Usuário logado
 * @var array<string,string>                   $config      Configurações
 * @var array<int,array{id,name,color,points,status,found_count}> $teams
 * @var array<int,list<array{id,code,name,found_at}>>             $foundByTeam
 */

$user = $user ?? [];
$config = $config ?? [];
$teams = $teams ?? [];
$foundByTeam = $foundByTeam ?? [];

// Mapear cores para hex
$colorMap = [
    'laranja' => '#F97316',
    'preta'   => '#1E293B',
];
?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
/* ================================================================
   DASHBOARD: MAPA + SIDEBAR DE GESTÃO
   ================================================================ */
.db-wrapper {
    display: flex;
    gap: 0;
    height: calc(100vh - 128px);
    min-height: 500px;
    margin: -32px;
    overflow: hidden;
}

/* ---- MAPA ---- */
.db-map-container {
    flex: 1;
    min-width: 0;
    position: relative;
    background: #050B12;
}
#db-map {
    width: 100%;
    height: 100%;
}

.db-map-error {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(5, 11, 18, 0.92);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #f5a6a0;
    padding: 14px 24px;
    border-radius: 12px;
    font-size: 0.88rem;
    text-align: center;
    display: none;
    z-index: 500;
    backdrop-filter: blur(8px);
}

.db-map-live {
    position: absolute;
    top: 12px;
    right: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(5, 11, 18, 0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 5px 12px;
    border-radius: 20px;
    z-index: 400;
}
.db-map-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ef4444;
    animation: dbPulse 1.5s ease-in-out infinite;
}
@keyframes dbPulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
    50%      { opacity: .7; box-shadow: 0 0 0 5px rgba(239,68,68,0); }
}

/* ---- SIDEBAR ---- */
.db-sidebar {
    width: 440px;
    flex-shrink: 0;
    background: #0A1724;
    border-left: 1px solid rgba(249,115,22,0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.db-sidebar-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.db-sidebar-scroll::-webkit-scrollbar { width: 5px; }
.db-sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.db-sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(249,115,22,0.25); border-radius: 4px; }

/* ---- TEAM CARD ---- */
.db-card {
    background: rgba(14, 31, 48, 0.92);
    border: 1px solid rgba(249, 115, 22, 0.12);
    border-radius: 14px;
    padding: 18px;
    animation: dbFadeIn 0.4s cubic-bezier(0.22,1,0.36,1) both;
}
.db-card:nth-child(2) { animation-delay: 0.08s; }

@keyframes dbFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.db-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(249,115,22,0.08);
}

.db-card-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 10px currentColor;
}

.db-card-info {
    flex: 1;
    min-width: 0;
}

.db-card-name {
    font-family: 'Pirata One', Georgia, cursive;
    font-size: 1.15rem;
    line-height: 1.2;
}

.db-card-name.color-laranja { color: #F97316; }
.db-card-name.color-preta   { color: #94a3b8; }

.db-card-status {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-top: 2px;
}
.db-card-status.online  { color: #22C55E; }
.db-card-status.offline { color: #EF4444; }

.db-card-points {
    font-family: 'Pirata One', Georgia, cursive;
    font-size: 2.4rem;
    line-height: 1;
    text-align: right;
}
.db-card-points.color-laranja { color: #F97316; }
.db-card-points.color-preta   { color: #94a3b8; }

.db-card-meta {
    font-size: 0.75rem;
    color: rgba(247,236,212,0.35);
    text-align: right;
    margin-top: 2px;
}

/* ---- POINTS FORM ---- */
.db-points-form {
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(249,115,22,0.08);
}

.db-form-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(247,236,212,0.45);
    margin-bottom: 8px;
    font-weight: 600;
}

.db-quick-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.db-quick-row .db-delta-input {
    flex: 0 0 84px;
    min-width: 0;
}

.db-quick-row .db-btn-quick {
    flex: 1;
    min-width: 0;
    padding: 8px 2px;
}

.db-btn-quick {
    flex: 1;
    min-width: 55px;
    padding: 7px 4px;
    border: 1px solid rgba(249,115,22,0.2);
    border-radius: 8px;
    background: rgba(249,115,22,0.06);
    color: #f7ecd4;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}
.db-btn-quick:hover {
    background: rgba(249,115,22,0.15);
    border-color: rgba(249,115,22,0.4);
    transform: translateY(-1px);
}
.db-btn-quick.neg {
    color: #ef4444;
    border-color: rgba(239,68,68,0.35);
    background: rgba(239,68,68,0.12);
}
.db-btn-quick.neg:hover {
    background: rgba(239,68,68,0.25);
    border-color: rgba(239,68,68,0.6);
}
.db-btn-quick.pos {
    color: #22C55E;
    border-color: rgba(34,197,94,0.35);
    background: rgba(34,197,94,0.12);
}
.db-btn-quick.pos:hover {
    background: rgba(34,197,94,0.25);
    border-color: rgba(34,197,94,0.6);
}

.db-form-row {
    display: flex;
    gap: 6px;
}

.db-input {
    flex: 1;
    padding: 8px 10px;
    border: 1px solid rgba(249,115,22,0.2);
    border-radius: 8px;
    background: rgba(5,11,18,0.6);
    color: #f7ecd4;
    font-size: 0.85rem;
    outline: none;
    transition: border-color 0.15s;
}
.db-input:focus {
    border-color: rgba(249,115,22,0.5);
}
.db-input::placeholder {
    color: rgba(247,236,212,0.25);
}

.db-btn-apply {
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    background: #F97316;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
}
.db-btn-apply:hover {
    background: #ea580c;
}

/* ---- TREASURES ---- */
.db-treasures-heading {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(247,236,212,0.45);
    margin-bottom: 8px;
    font-weight: 600;
}

.db-treasure-empty {
    font-size: 0.82rem;
    color: rgba(247,236,212,0.3);
    font-style: italic;
}

.db-treasure-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.db-treasure-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: rgba(5,11,18,0.45);
    border: 1px solid rgba(249,115,22,0.06);
    border-radius: 8px;
    font-size: 0.8rem;
}

.db-treasure-icon {
    font-size: 1rem;
    flex-shrink: 0;
}

.db-treasure-info {
    flex: 1;
    min-width: 0;
}
.db-treasure-name {
    font-weight: 600;
    color: #f7ecd4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.db-treasure-code {
    font-size: 0.68rem;
    color: rgba(247,236,212,0.35);
    font-family: 'Inter', monospace;
}
.db-treasure-date {
    font-size: 0.65rem;
    color: rgba(247,236,212,0.25);
    margin-top: 1px;
}

.db-btn-disqualify {
    padding: 4px 10px;
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 6px;
    background: rgba(239,68,68,0.08);
    color: #ef4444;
    font-size: 0.68rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s;
    flex-shrink: 0;
}
.db-btn-disqualify:hover {
    background: rgba(239,68,68,0.2);
    border-color: rgba(239,68,68,0.5);
}

.db-btn-selfie {
    padding: 4px 8px;
    border: 1px solid rgba(249,115,22,0.4);
    border-radius: 6px;
    background: rgba(249,115,22,0.12);
    color: #F97316;
    font-size: 0.85rem;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.15s;
}
.db-btn-selfie:hover {
    background: rgba(249,115,22,0.25);
    border-color: #F97316;
}

/* ---- TEAM PIN (flat, copiado do telão) ---- */
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
    font-size: 17px;
    font-weight: 800;
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
.team-pin-preta { background: #1E293B; border: 1px solid rgba(148,163,184,0.3); }
.team-pin-preta .team-pin-tail { border-top-color: #1E293B; }

/* ---- RESPONSIVE ---- */
@media (max-width: 900px) {
    .db-wrapper {
        flex-direction: column;
        height: auto;
        min-height: auto;
    }
    .db-map-container {
        height: 50vh;
        min-height: 320px;
    }
    .db-sidebar {
        width: 100%;
        border-left: none;
        border-top: 1px solid rgba(249,115,22,0.1);
    }
    .db-sidebar-scroll {
        max-height: 60vh;
    }
}

@media (max-width: 640px) {
    .db-wrapper {
        margin: -16px;
    }
    .db-map-container {
        height: 45vh;
        min-height: 260px;
    }
    .db-sidebar-scroll {
        padding: 14px;
    }
    .db-card {
        padding: 14px;
    }
    .db-card-points {
        font-size: 1.8rem;
    }
}
</style>

<!-- ============================================================
     DASHBOARD: MAPA + GESTÃO
     ============================================================ -->
<div class="db-wrapper">

    <!-- MAPA -->
    <div class="db-map-container">
        <div id="db-map"></div>
        <div class="db-map-live">
            <span class="db-map-live-dot"></span> AO VIVO
        </div>
        <div class="db-map-error" id="db-map-error">Erro ao carregar dados. Tentando novamente...</div>
    </div>

    <!-- SIDEBAR -->
    <div class="db-sidebar">
        <div class="db-sidebar-scroll">

            <!-- Mensagem para todas as equipes -->
            <div class="db-card" style="border:1px solid rgba(34,197,94,0.25); margin-bottom:14px;">
                <div class="db-card-header">
                    <div class="db-card-dot" style="background:#22C55E;color:#22C55E"></div>
                    <div class="db-card-info">
                        <div class="db-card-name" style="color:#22C55E;">Enviar mensagem</div>
                        <div class="db-card-status online">Notifica todas as equipes</div>
                    </div>
                </div>
                <form method="post" action="/admin/mensagem">
                    <?= csrf_field() ?>
                    <div class="db-form-row" style="flex-direction:column; gap:8px;">
                        <input type="text" name="message" class="db-input" placeholder="Mensagem para as equipes..." maxlength="500" required>
                        <button type="submit" class="db-btn-apply" style="width:100%; background:linear-gradient(135deg,#22C55E,#168a3a);">
                            📣 Enviar mensagem para as equipes
                        </button>
                    </div>
                </form>
            </div>

            <?php foreach ($teams as $team):
                $colorKey   = (string) ($team['color'] ?? '');
                $teamId     = (int)    ($team['id'] ?? 0);
                $teamName   = (string) ($team['name'] ?? '');
                $teamPoints = (int)    ($team['points'] ?? 0);
                $teamStatus = (string) ($team['status'] ?? 'playing');
                $foundCount = (int)    ($team['found_count'] ?? 0);
                $foundItems = $foundByTeam[$teamId] ?? [];
                $hexColor   = $colorMap[$colorKey] ?? '#94a3b8';
            ?>
            <div class="db-card">
                <!-- Header -->
                <div class="db-card-header">
                    <div class="db-card-dot" style="background:<?= e($hexColor) ?>;color:<?= e($hexColor) ?>"></div>
                    <div class="db-card-info">
                        <div class="db-card-name color-<?= e($colorKey) ?>"><?= e($teamName) ?></div>
                        <div class="db-card-status <?= $teamStatus === 'finished' ? 'online' : 'offline' ?>">
                            <?= $teamStatus === 'finished' ? 'Terminou' : 'Jogando' ?>
                        </div>
                    </div>
                    <div class="db-card-points color-<?= e($colorKey) ?>"><?= $teamPoints ?></div>
                </div>
                <div class="db-card-meta">Tesouros encontrados: <?= $foundCount ?></div>

                <!-- Ajustar Pontos -->
                <form method="post" action="/admin/pontos" class="db-points-form" data-delta-input>
                    <?= csrf_field() ?>
                    <input type="hidden" name="team_id" value="<?= $teamId ?>">

                    <div class="db-form-label">Ajustar Pontos</div>

                    <div class="db-quick-row">
                        <input type="number" name="delta" class="db-input db-delta-input" placeholder="Pontos" required>
                        <button type="button" class="db-btn-quick neg" data-delta="-20">-20</button>
                        <button type="button" class="db-btn-quick neg" data-delta="-10">-10</button>
                        <button type="button" class="db-btn-quick pos" data-delta="+10">+10</button>
                        <button type="button" class="db-btn-quick pos" data-delta="+20">+20</button>
                    </div>

                    <div class="db-form-row">
                        <input type="text" name="reason" class="db-input" placeholder="Motivo (opcional)">
                        <button type="submit" class="db-btn-apply">Aplicar</button>
                    </div>
                </form>

                <!-- Tesouros Encontrados -->
                <div class="db-treasures-heading">Tesouros Encontrados (<?= $foundCount ?>)</div>

                <?php if (empty($foundItems)): ?>
                    <div class="db-treasure-empty">Nenhum tesouro encontrado ainda</div>
                <?php else: ?>
                    <div class="db-treasure-list">
                        <?php foreach ($foundItems as $treasure): ?>
                        <div class="db-treasure-item">
                            <span class="db-treasure-icon">&#x1F3AF;</span>
                            <div class="db-treasure-info">
                                <div class="db-treasure-name"><?= e((string)($treasure['name'] ?? '')) ?></div>
                                <div class="db-treasure-code"><?= e((string)($treasure['code'] ?? '')) ?></div>
                                <?php if (!empty($treasure['found_at'])): ?>
                                <div class="db-treasure-date"><?= e((string)$treasure['found_at']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($treasure['selfie_path'])): ?>
                            <button type="button" class="db-btn-selfie" data-selfie="<?= e((string)$treasure['selfie_path']) ?>"
                                    title="Abrir selfie">📷</button>
                            <?php endif; ?>
                            <form method="post" action="/admin/desclassificar"
                                  onsubmit="return confirm('Desclassificar este tesouro da <?= e($teamName) ?>? Os pontos serão revertidos e o tesouro não poderá ser refeito.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="team_id" value="<?= $teamId ?>">
                                <input type="hidden" name="treasure_id" value="<?= (int)($treasure['id'] ?? 0) ?>">
                                <button type="submit" class="db-btn-disqualify">Desclassificar</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <!-- Modal da selfie -->
            <div id="selfie-modal" style="display:none; position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; padding:20px;">
                <div style="max-width:560px; width:100%; text-align:center;">
                    <img id="selfie-modal-img" src="" alt="Selfie" style="max-width:100%; max-height:80vh; border-radius:12px; border:2px solid rgba(249,115,22,0.4);">
                    <div style="margin-top:14px;">
                        <button id="selfie-modal-close" class="db-btn-apply" style="background:linear-gradient(135deg,#F97316,#EA580C);">Fechar</button>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var modal = document.getElementById('selfie-modal');
                var img = document.getElementById('selfie-modal-img');
                var close = document.getElementById('selfie-modal-close');
                if (!modal || !img || !close) return;
                document.querySelectorAll('[data-selfie]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        img.src = this.getAttribute('data-selfie');
                        modal.style.display = 'flex';
                    });
                });
                close.addEventListener('click', function () { modal.style.display = 'none'; img.src = ''; });
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) { modal.style.display = 'none'; img.src = ''; }
                });
            })();
            </script>

            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- ============================================================
     MAP SCRIPT
     ============================================================ -->
<script>
(function () {
    'use strict';

    var COLORS = { laranja: '#F97316', preta: '#94a3b8' };
    var teamMarkers = {};
    var map = null;

    /* ---- INIT MAP ---- */
    function initMap() {
        map = L.map('db-map', {
            center: [-22.01392755007763, -47.42434367236048],
            zoom: 15,
            zoomControl: true,
            attributionControl: true
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
    }

    /* ---- FETCH + RENDER ---- */
    function loadPositions() {
        fetch('/api/telao?t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (!data.success) throw new Error('API error');
                document.getElementById('db-map-error').style.display = 'none';
                renderPins(data.teams || []);
            })
            .catch(function (err) {
                console.error('[Dashboard] fetch error:', err);
                document.getElementById('db-map-error').style.display = 'block';
            });
    }

    function renderPins(teams) {
        var seen = {};
        teams.forEach(function (team) {
            var key = (team.color || '').toLowerCase();
            seen[key] = true;

            if (team.online && team.last_location) {
                var lat = team.last_location.lat;
                var lng = team.last_location.lng;
                var colorName = (key === 'laranja') ? 'Laranja' : 'Preta';
                var icon = L.divIcon({
                    className: 'team-pin-wrap',
                    html: '<div class="team-pin team-pin-' + key + '">'
                        + '<span class="team-pin-label">Equipe</span>'
                        + '<span class="team-pin-name">' + colorName + '</span>'
                        + '<div class="team-pin-tail"></div>'
                        + '</div>',
                    iconSize: [130, 52],
                    iconAnchor: [65, 52],
                    popupAnchor: [0, -48]
                });

                if (teamMarkers[key]) {
                    teamMarkers[key].setLatLng([lat, lng]);
                    teamMarkers[key].setIcon(icon);
                } else {
                    teamMarkers[key] = L.marker([lat, lng], { icon: icon }).addTo(map);
                }
                teamMarkers[key].bindPopup(
                    '<strong>' + (team.name || colorName) + '</strong><br>'
                    + 'Pontos: ' + (team.points || 0) + '<br>&#x25CF; ONLINE'
                );
            } else if (teamMarkers[key]) {
                map.removeLayer(teamMarkers[key]);
                delete teamMarkers[key];
            }
        });

        /* Remove markers de cores que não existem mais */
        Object.keys(teamMarkers).forEach(function (k) {
            if (!seen[k]) {
                map.removeLayer(teamMarkers[k]);
                delete teamMarkers[k];
            }
        });
    }

    /* ---- QUICK BUTTONS: aplicam os pontos imediatamente ---- */
    document.querySelectorAll('[data-delta-input]').forEach(function (form) {
        var deltaInput = form.querySelector('input[name="delta"]');
        form.querySelectorAll('[data-delta]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = this.getAttribute('data-delta');
                // Remove o '+' (o input type=number pode rejeitar o sinal)
                deltaInput.value = val.charAt(0) === '+' ? val.slice(1) : val;
                form.submit();
            });
        });
    });

    /* ---- INIT ---- */
    initMap();
    loadPositions();
    setInterval(loadPositions, 5000);
})();
</script>
