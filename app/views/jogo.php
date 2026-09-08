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
                <span style="color: #5fd99f;">▶</span>
            <?php else: ?>
                <span style="color: #f5c542;">⏸</span>
            <?php endif; ?>
        </div>
        <div class="game-stat-label"><?= $gameActive ? 'Partida ativa' : 'Pausada' ?></div>
    </div>

    <div class="game-stat-card">
        <div class="game-stat-value"><?= count($teams) ?></div>
        <div class="game-stat-label">Equipes</div>
    </div>

    <div class="game-stat-card">
        <div class="game-stat-value"><?= $order === 'aleatorio' ? '🎲' : '📋' ?></div>
        <div class="game-stat-label">Ordem <?= $order === 'aleatorio' ? 'aleatória' : 'estabelecida' ?></div>
    </div>

    <?php if ($winner !== ''): ?>
    <div class="game-stat-card">
        <div class="game-stat-value">🏆</div>
        <div class="game-stat-label"><?= e($winner) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($winner !== ''): ?>
    <div class="settings-card" style="border-left: 4px solid #27ae60;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 2rem;">🏆</span>
            <div>
                <h3 style="margin: 0; font-family: 'Pirata One', 'Georgia', cursive; color: #f5c542; font-size: 1.2rem;">
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
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
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
                                    <span class="badge badge-success" style="margin-left: 6px;">🏆 Vencedora</span>
                                <?php endif; ?>
                            </h3>
                            <div class="treasure-card-badges">
                                <span class="badge badge-info">⭐ <?= $points ?> pontos</span>
                                <?php if ($isFinished): ?>
                                    <span class="badge badge-success">✔ Terminou</span>
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
