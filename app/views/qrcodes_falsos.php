<?php

/**
 * Página "QR Codes Falsos" — iscas com mensagens.
 *
 * Estes QR codes NÃO identificam tesouro nenhum: quando a equipe lê um deles
 * no app, aparece a mensagem cadastrada e o jogo volta para a tela inicial.
 *
 * @var array<int, array<string, mixed>> $decoys
 * @var array<string, mixed>             $old
 */

$decoys = $decoys ?? [];
$old = $old ?? [];
?>
<div class="page-header">
    <h1 class="page-title">QR Codes Falsos</h1>
    <p class="page-subtitle">
        Iscas para esconder no caminho: o app mostra a mensagem e a equipe volta para o início.
    </p>
</div>

<!-- ── Como funciona ──────────────────────────────────────── -->
<div class="settings-card" style="border:1px solid rgba(249,115,22,0.3); background:rgba(249,115,22,0.05);">
    <h2 class="settings-card-title">
        <?= icon('help_outline', 20) ?>
        Como funciona
    </h2>
    <ul style="color:rgba(247,236,212,0.7); font-size:0.9rem; line-height:1.9; margin:0; padding-left:18px;">
        <li>Gere um QR code com a mensagem que quiser.</li>
        <li><strong>Baixe o SVG</strong> (igual aos QR dos tesouros) e esconda no caminho.</li>
        <li>Quando a equipe ler, o app mostra a mensagem e
            <strong style="color:#F97316;">volta para a tela inicial</strong>.</li>
        <li>🚫 Ele <strong>não identifica tesouro nenhum</strong> — não dá pontos e não avança o jogo.</li>
    </ul>
</div>

<!-- ── Criar ──────────────────────────────────────────────── -->
<div class="settings-card">
    <h2 class="settings-card-title">
        <?= icon('add_link', 20) ?>
        Criar um QR code falso
    </h2>

    <form method="post" action="/qrcodes-falsos">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="message">Mensagem que a equipe vai ver *</label>
            <textarea id="message" name="message" class="form-input" rows="3" maxlength="500"
                      required placeholder="Ex.: Vocês caíram numa armadilha do Quico! Voltem para o início."><?= e((string) ($old['message'] ?? '')) ?></textarea>
            <div class="error-inline"></div>
            <p class="form-help-text">
                Aparece em um popup no app, bem chamativo, e o jogo volta para a tela inicial.
            </p>
        </div>

        <div class="settings-actions">
            <button type="submit" class="btn btn-primary" style="width:auto;">
                <?= icon('qr_code_2', 18) ?>
                Gerar QR code falso
            </button>
        </div>
    </form>
</div>

<!-- ── Lista ──────────────────────────────────────────────── -->
<div class="settings-card">
    <h2 class="settings-card-title">
        <?= icon('list', 20) ?>
        QR codes falsos criados (<?= count($decoys) ?>)
    </h2>

    <?php if ($decoys === []): ?>
        <p class="form-help-text" style="margin:0;">
            Nenhum QR code falso criado ainda.
        </p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:16px;">
            <?php foreach ($decoys as $decoy): ?>
                <?php
                $id = (int) ($decoy['id'] ?? 0);
                $msg = (string) ($decoy['message'] ?? '');
                $svg = (string) ($decoy['qr_svg_path'] ?? '');
                ?>
                <div style="display:flex; gap:16px; align-items:flex-start; padding:16px; background:rgba(5,11,18,0.4); border:1px solid rgba(249,115,22,0.12); border-radius:14px; flex-wrap:wrap;">
                    <?php if ($svg !== ''): ?>
                        <img src="<?= e($svg) ?>" alt="QR code falso" width="96" height="96"
                             style="width:96px;height:96px;border-radius:10px;background:#fff;padding:6px;flex-shrink:0;">
                    <?php else: ?>
                        <div class="qr-thumb qr-thumb-placeholder" style="width:96px;height:96px;flex-shrink:0;">
                            <?= icon('qr_code_2', 32) ?>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1; min-width:200px;">
                        <p style="margin:0 0 8px; color:#f7ecd4; font-size:0.92rem; line-height:1.6;">
                            <?= e($msg) ?>
                        </p>
                        <code class="qr-code-read-only"><?= e((string) ($decoy['content'] ?? '')) ?></code>

                        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if ($svg !== ''): ?>
                                <a href="<?= e($svg) ?>" download="QR_falso_<?= $id ?>.svg" class="download-btn">
                                    <?= icon('download', 14) ?>
                                    Baixar QR (SVG)
                                </a>
                            <?php endif; ?>

                            <form method="post" action="/qrcodes-falsos/<?= $id ?>/excluir" class="form-inline"
                                  data-confirm="Excluir este QR code falso? A mensagem dele deixa de funcionar.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-sm"
                                        style="padding:6px 12px; font-size:.78rem;">
                                    <?= icon('delete', 14) ?>
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
