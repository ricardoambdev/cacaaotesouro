<?php

/**
 * Página de Tesouros — listagem.
 *
 * @var array<int, array<string, mixed>> $treasures Tesouros cadastrados
 * @var array<int, array<string, mixed>> $teams     Equipes (indexadas por id)
 * @var array<string, mixed>             $game      Configurações do jogo
 * @var array{name:string}               $user      Usuário logado
 */

$user = $user ?? [];
$teams = $teams ?? [];
$game = $game ?? [];
$treasures = $treasures ?? [];
$name = (string) ($user['name'] ?? '');

$isRandomOrder = ($game['treasureOrder'] ?? 'estabelecida') === 'aleatorio';
$orderLabel = $isRandomOrder ? 'Aleatória por equipe' : 'Estabelecida (sort_order)';
$gameActive = ($game['gameActive'] ?? '0') === '1';
?>
<div class="page-header">
    <h1 class="page-title">Tesouros</h1>
    <p class="page-subtitle">Gerencie os tesouros escondidos do jogo do Quico.</p>
</div>

<?php if ($gameActive): ?>
    <div class="badge badge-success" style="margin-bottom: 16px; display: inline-block;">▶ Partida ativa</div>
<?php else: ?>
    <div class="badge badge-warning" style="margin-bottom: 16px; display: inline-block;">⏸ Partida pausada</div>
<?php endif; ?>

<div class="badge badge-info" style="margin-bottom: 16px; display: inline-block;">Ordem: <?= e($orderLabel) ?></div>

<?php if (!empty($game['winner'])): ?>
    <div class="badge badge-success" style="margin-bottom: 16px; display: inline-block;">
        🏆 Vencedora: <?= e((string) $game['winner']['name']) ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <a href="/tesouros/novo" class="btn btn-primary" style="width: auto;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Cadastrar tesouro
    </a>
    <button type="button" id="btn-save-order" class="btn btn-ghost btn-sm" style="margin-left: 8px;" disabled>
        💾 Salvar nova ordem
    </button>
</div>

<?php if ($treasures === []): ?>
    <div class="empty-state">
        <img src="/assets/imagens/tesouro.gif" alt="Baú de tesouro" class="empty-state-icon">

        <h2 class="empty-state-title">Nenhum tesouro cadastrado ainda</h2>
        <p class="empty-state-text">Os tesouros que você cadastrar aparecerão aqui, com o QR code gerado automaticamente.</p>
        <a href="/tesouros/novo" class="btn btn-primary">
            Cadastrar o primeiro tesouro
        </a>
    </div>
<?php else: ?>
    <!-- CSRF token for JS fetch -->
    <meta id="csrf-token-data" data-csrf="<?= e(csrf_token()) ?>">

    <?php if ($isRandomOrder): ?>
        <div class="drag-disabled-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            A ordem é <strong style="margin: 0 4px;">aleatória</strong> — os tesouros são sorteados por equipe. Arrastar está desabilitado.
        </div>
    <?php endif; ?>

    <div class="treasure-list" id="treasure-list" <?= $isRandomOrder ? 'data-random-order="true"' : '' ?>>
        <?php foreach ($treasures as $index => $treasure): ?>
            <?php $isActive = (int) ($treasure['active'] ?? 0) === 1; ?>
            <?php
            $lat = $treasure['lat'] ?? null;
            $lng = $treasure['lng'] ?? null;
            $hasGps = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
            $progress = $treasure['progress'] ?? [];
            ?>
            <div class="treasure-card" data-treasure-id="<?= (int) $treasure['id'] ?>" <?= !$isRandomOrder ? 'draggable="true"' : '' ?>>
                <!-- Reorder handle -->
                <div class="treasure-card-order" title="<?= $isRandomOrder ? 'Ordem aleatória' : 'Arraste para reordenar' ?>">
                    <span class="drag-handle">⠿</span>
                    <span class="order-badge"><?= (int) ($treasure['sort_order'] ?? $index + 1) ?></span>
                </div>

                <!-- QR Thumbnail + Download -->
                <?php if (($treasure['qr_svg_path'] ?? '') !== ''): ?>
                    <div class="treasure-card-qr">
                        <img src="<?= e((string) $treasure['qr_svg_path']) ?>" alt="QR code" class="qr-thumb">
                    </div>
                <?php endif; ?>

                <!-- Body -->
                <div class="treasure-card-body">
                    <div class="treasure-card-header">
                        <h3 class="treasure-card-name">
                            <span class="badge badge-info"><?= e((string) ($treasure['code'] ?? '')) ?></span>
                            <?= e((string) ($treasure['name'] ?? '')) ?>
                        </h3>

                        <div class="treasure-card-badges">
                            <?php if ($isActive): ?>
                                <span class="badge badge-success">✔ Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Inativo</span>
                            <?php endif; ?>

                            <?php if ($hasGps): ?>
                                <span class="badge badge-success">📍 <?= e((string) $lat) ?>, <?= e((string) $lng) ?></span>
                            <?php else: ?>
                                <span class="badge badge-warning">📍 Coordenada pendente</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="treasure-card-desc">
                        <?= e((string) ($treasure['description'] ?? '')) ?>
                    </p>

                    <p class="treasure-card-desc" style="opacity:.75;font-size:.92em;">
                        💡 Dica: <?= e((string) ($treasure['clue'] ?? '')) ?>
                    </p>

                    <!-- Team Progress Markers -->
                    <div class="treasure-card-progress">
                        <?php
                        // Collect all team colors to always show both markers
                        $allColors = ['preta', 'laranja'];
                        foreach ($allColors as $color):
                            $found = false;
                            $foundAt = '';
                            foreach ($teams as $team) {
                                if ((string) ($team['color'] ?? '') === $color) {
                                    $row = $progress[$color] ?? null;
                                    if ($row !== null && (int) ($row['riddle_correct'] ?? 0) === 1) {
                                        $found = true;
                                        $foundAt = (string) ($row['found_at'] ?? '');
                                    }
                                    break;
                                }
                            }
                        ?>
                            <span class="team-marker marker-<?= e($color) ?> <?= $found ? 'marker-found' : 'marker-missing' ?>">
                                <span class="team-marker-dot"></span>
                                <span class="team-marker-label"><?= e($color) ?></span>
                                <?php if ($found): ?>
                                    <span class="team-marker-status">✔ <?= e($foundAt) ?></span>
                                <?php else: ?>
                                    <span class="team-marker-status">Não encontrado</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- QR Download -->
                    <?php if (($treasure['qr_svg_path'] ?? '') !== ''): ?>
                        <div class="treasure-card-qr-footer">
                            <a href="<?= e((string) $treasure['qr_svg_path']) ?>"
                               download="QR_<?= e((string) ($treasure['code'] ?? 'tesouro')) ?>.svg"
                               class="download-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Baixar QR
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="treasure-card-actions">
                        <a href="/tesouros/<?= (int) $treasure['id'] ?>/editar" class="btn btn-ghost btn-sm">Editar</a>
                        <form method="post" action="/tesouros/<?= (int) $treasure['id'] ?>/excluir" class="form-inline"
                              data-confirm="Excluir este tesouro? Esta ação não pode ser desfeita.">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
