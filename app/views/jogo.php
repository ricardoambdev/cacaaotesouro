<?php

/**
 * Página Jogo — estado geral da partida.
 *
 * @var array<int, array<string, mixed>> $teams Equipes com pontos/encontrados
 * @var array<string, mixed>             $game  Estado do jogo
 */

$teams = $teams ?? [];
$game = $game ?? [];

$gameActive = ($game['gameActive'] ?? '0') === '1';
$winner = (string) ($game['winner'] ?? '');
$order = (string) ($game['treasureOrder'] ?? 'estabelecida');

// Count total treasures for progress calculation
$totalTreasures = 0;
foreach ($teams as $t) {
    $found = (int) ($t['found_count'] ?? 0);
    if ($found > $totalTreasures) {
        $totalTreasures = $found;
    }
}
// Use the maximum found_count across teams as a rough total
// (actual total would need a separate query, but this is a visual estimate)
$maxFound = 0;
foreach ($teams as $t) {
    $c = (int) ($t['found_count'] ?? 0);
    if ($c > $maxFound) $maxFound = $c;
}
?>
<div class="page-header">
    <h1 class="page-title">Jogo</h1>
    <p class="page-subtitle">Estado geral da partida em andamento.</p>
</div>

<!-- Status Summary -->
<div class="game-stat-grid">
    <div class="game-stat-card">
        <div class="game-stat-value">
            <?php if ($gameActive): ?>
                <?= icon('play_arrow', 28) ?>
            <?php else: ?>
                <?= icon('pause', 28) ?>
            <?php endif; ?>
        </div>
        <div class="game-stat-label"><?= $gameActive ? 'Partida ativa' : 'Pausada' ?></div>
    </div>

    <div class="game-stat-card">
        <div class="game-stat-value"><?= count($teams) ?></div>
        <div class="game-stat-label">Equipes</div>
    </div>

    <div class="game-stat-card">
        <div class="game-stat-value"><?= $order === 'aleatorio' ? icon('casino', 28) : icon('list', 28) ?></div>
        <div class="game-stat-label">Ordem <?= $order === 'aleatorio' ? 'aleatória' : 'estabelecida' ?></div>
    </div>

    <?php if ($winner !== ''): ?>
    <div class="game-stat-card">
        <div class="game-stat-value"><?= icon('emoji_events', 28) ?></div>
        <div class="game-stat-label"><?= e($winner) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($winner !== ''): ?>
    <div class="settings-card" style="border-left: 4px solid #27ae60;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <?= icon('emoji_events', 32) ?>
            <div>
                <h3 style="margin: 0; font-family: 'Pirata One', 'Georgia', cursive; color: #F97316; font-size: 1.2rem;">
                    Equipe vencedora: <?= e($winner) ?>
                </h3>
                <p style="margin: 4px 0 0; opacity: 0.6; font-size: 0.88rem;">
                    Parabéns! Esta equipe completou o desafio.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Team Cards -->
<div class="settings-card">
    <h2 class="settings-card-title">
        <?= icon('groups', 20) ?>
        Equipes
    </h2>

    <?php if ($teams === []): ?>
        <p style="opacity:.7;">Nenhuma equipe cadastrada.</p>
    <?php else: ?>
        <div class="treasure-list">
            <?php foreach ($teams as $index => $team): ?>
                <?php
                $teamColor = (string) ($team['color'] ?? '');
                $points = (int) ($team['points'] ?? 0);
                $foundCount = (int) ($team['found_count'] ?? 0);
                $currentStep = (int) ($team['current_step'] ?? 0);
                $status = (string) ($team['status'] ?? 'playing');
                $isFinished = $status === 'finished';
                $isWinner = $winner !== '' && strtolower($winner) === strtolower((string) ($team['name'] ?? ''));
                $fillClass = $teamColor === 'preta' ? 'fill-preta' : 'fill-laranja';
                ?>
                <div class="treasure-card" style="<?= $isWinner ? 'border-color: rgba(39, 174, 96, 0.4);' : '' ?> <?= $teamColor === 'preta' ? 'border-left: 4px solid #7f8c8d;' : 'border-left: 4px solid #e67e22;' ?>">
                    <div class="treasure-card-body">
                        <div class="treasure-card-header">
                            <h3 class="treasure-card-name">
                                <span class="badge <?= $teamColor === 'preta' ? 'badge-dark' : 'badge-orange' ?>" style="<?= $teamColor === 'preta' ? 'background:rgba(127,140,141,0.2);color:#bdc3c7;border:1px solid rgba(127,140,141,0.35);' : 'background:rgba(230,126,34,0.2);color:#f39c12;border:1px solid rgba(230,126,34,0.35);' ?>">
                                    <?= e($teamColor) ?>
                                </span>
                                <?= e((string) ($team['name'] ?? '')) ?>
                                <?php if ($isWinner): ?>
                                    <span class="badge badge-success" style="margin-left: 6px;"><?= icon('emoji_events', 14) ?> Vencedora</span>
                                <?php endif; ?>
                            </h3>
                            <div class="treasure-card-badges">
                                <span class="badge badge-info"><?= icon('stars', 14) ?> <?= $points ?> pontos</span>
                                <?php if ($isFinished): ?>
                                    <span class="badge badge-success"><?= icon('check', 14) ?> Terminou</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Em jogo</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="treasure-card-desc" style="opacity:.85;">
                            Tesouros encontrados: <strong><?= $foundCount ?></strong>
                            • Etapa atual: <strong><?= $currentStep ?></strong>
                            <?php if (!empty($team['finished_at'])): ?>
                                • Finalizou em <?= e((string) $team['finished_at']) ?>
                            <?php endif; ?>
                        </p>

                        <!-- Progress bar -->
                        <?php if ($maxFound > 0): ?>
                            <div class="game-progress-bar">
                                <div class="game-progress-fill <?= $fillClass ?>" style="width: <?= round(($foundCount / $maxFound) * 100) ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     TELÃO — LINK SECRETO (aberto numa única máquina, no evento)
     ═══════════════════════════════════════════════════════════════ -->
<div class="settings-card" style="margin-top: 24px;">
    <h2 class="settings-card-title">
        <?= icon('tv', 20) ?>
        Telão do evento
    </h2>

    <p class="form-help-text" style="margin-top: 0; margin-bottom: 14px;">
        Endereço público do telão. Abra no <strong>projetor</strong> e deixe em tela cheia —
        pode ser acessado de qualquer lugar, sem login.
    </p>

    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <div class="api-url-box" style="margin-bottom: 10px;">
                <span class="url-text"><?= e($telaoUrl ?? '') ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-copy" data-copy="<?= e($telaoUrl ?? '') ?>" style="margin-bottom: 8px;">
                <?= icon('content_copy', 14) ?> Copiar link
            </button>
        </div>

        <div style="text-align: center; flex-shrink: 0;">
            <div style="background: #fff; border-radius: 10px; padding: 8px; display: inline-block; border: 1px solid rgba(249,115,22,0.15);">
                <img src="/telao/qr.svg" alt="QR code do telão" width="180" height="180">
            </div>
            <div style="font-size: 0.72rem; color: rgba(247,236,212,0.4); margin-top: 6px;">QR Code do telão</div>
        </div>
    </div>

    <div style="margin-top: 16px; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?= e($telaoUrl ?? '') ?>" target="_blank" rel="noopener" class="btn btn-primary btn-auto btn-sm" style="background:linear-gradient(135deg,#22C55E,#168a3a);">
            <?= icon('open_in_new', 16) ?>
            Abrir o telão
        </a>
    </div>
</div>
