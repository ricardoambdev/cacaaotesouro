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
?>
<div class="page-header-row">
    <div class="page-header">
        <h1 class="page-title">Tesouros</h1>
        <p class="page-subtitle">Gerencie os tesouros escondidos do jogo do Quico.</p>
    </div>
    <a href="/tesouros/novo" class="btn btn-primary" style="width:auto;">
        <?= icon('add', 18) ?>
        Cadastrar tesouro
    </a>
</div>

<?php if (!empty($game['winner'])): ?>
    <div class="badge badge-success" style="margin-bottom: 16px; display: inline-block;">
        <?= icon('emoji_events', 16) ?> Vencedora: <?= e((string) $game['winner']['name']) ?>
    </div>
<?php endif; ?>

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
            <?= icon('info', 16) ?>
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
                        <a href="<?= e((string) $treasure['qr_svg_path']) ?>"
                           download="QR_<?= e((string) ($treasure['code'] ?? 'tesouro')) ?>.svg"
                           class="download-btn">
                            <?= icon('download', 14) ?>
                            Baixar QR
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Body -->
                <div class="treasure-card-body">
                    <div class="treasure-card-header">
                        <h3 class="treasure-card-name">
                            <span class="badge badge-info"><?= e((string) ($treasure['code'] ?? '')) ?></span>
                            Tesouro <?= (int) ($treasure['sort_order'] ?? $index + 1) ?>
                        </h3>
                        <?php if (trim((string) ($treasure['name'] ?? '')) !== ''): ?>
                            <div style="font-size:.78rem;color:rgba(247,236,212,0.45);margin-top:-2px;">
                                Cadastrado como: <?= e((string) $treasure['name']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="treasure-card-badges">
                            <?php if ($isActive): ?>
                                <span class="badge badge-success"><?= icon('check', 14) ?> Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Inativo</span>
                            <?php endif; ?>

                            <?php if ($hasGps): ?>
                                <span class="badge badge-success"><?= icon('place', 14) ?> <?= e((string) $lat) ?>, <?= e((string) $lng) ?></span>
                            <?php else: ?>
                                <span class="badge badge-warning"><?= icon('place', 14) ?> Coordenada pendente</span>
                            <?php endif; ?>

                            <?php if ((int) ($treasure['with_guardian'] ?? 0) === 1): ?>
                                <span class="badge badge-info" title="Deve ser encontrado na companhia dos responsáveis">
                                    <?= icon('family_restroom', 14) ?> Com responsáveis
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="treasure-card-desc">
                        <span style="color:#F97316; font-weight:600;">📍 Local:</span>
                        <?= e((string) ($treasure['description'] ?? '')) ?>
                        <span style="opacity:.5; font-size:.82em;">(só o admin vê)</span>
                    </p>

                    <!-- Team Progress Markers -->
                    <div class="treasure-card-progress">
                        <?php
                        // Collect all team colors to always show both markers
                        $allColors = ['preta', 'laranja'];
                        foreach ($allColors as $color):
                            $found = false;
                            $foundAt = '';
                            $earned = 0;
                            $isFirst = false;
                            foreach ($teams as $team) {
                                if ((string) ($team['color'] ?? '') === $color) {
                                    $row = $progress[$color] ?? null;
                                    if ($row !== null && (int) ($row['riddle_correct'] ?? 0) === 1) {
                                        $found = true;
                                        $foundAt = (string) ($row['found_at'] ?? '');
                                        $earned = (int) ($row['earned'] ?? 0);
                                        $isFirst = (int) ($row['first_bonus'] ?? 0) === 1;
                                    }
                                    break;
                                }
                            }
                        ?>
                            <span class="team-marker marker-<?= e($color) ?> <?= $found ? 'marker-found' : 'marker-missing' ?>">
                                <span class="team-marker-dot"></span>
                                <span class="team-marker-label"><?= e($color) ?></span>
                                <?php if ($found): ?>
                                    <span class="team-marker-status"><?= icon('check', 14) ?> <?= e($foundAt) ?> · +<?= $earned ?> pts</span>
                                    <?php if ($isFirst): ?>
                                        <span class="badge badge-success" title="Primeira equipe a encontrar este tesouro!"><?= icon('rocket_launch', 14) ?> 1º +10</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="team-marker-status">Não encontrado</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

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
