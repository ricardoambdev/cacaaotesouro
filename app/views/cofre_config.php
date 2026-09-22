<?php

/**
 * Página própria de configuração do COFRE no painel admin.
 *
 * Variáveis recebidas do GameController::vaultConfig():
 *
 * @var string $vaultCode         Código do cofre (9 dígitos ou vazio)
 * @var string $vaultMaxAttempts  Tentativas antes de bloquear (1–20)
 * @var string $vaultBlockMinutes Tempo de bloqueio em minutos (1–1440)
 * @var string $vaultBlockNextDay Bloquear até o dia seguinte (1/0)
 * @var string $vaultUrl          Link público do cofre
 * @var string $finalAnswer       Senha do desafio final (revelada pelo cofre)
 * @var array  $blockedIps        IPs bloqueados agora
 * @var string $vaultLat          Latitude do cofre (capturada pelo app admin)
 * @var string $vaultLng          Longitude do cofre
 * @var string $vaultRadius       Raio permitido em metros (10–5000)
 */

$vaultCode         = $vaultCode ?? '';
$vaultMaxAttempts  = $vaultMaxAttempts ?? '3';
$vaultBlockMinutes = $vaultBlockMinutes ?? '5';
$vaultBlockNextDay = $vaultBlockNextDay ?? '0';
$vaultUrl          = $vaultUrl ?? '';
$finalAnswer       = $finalAnswer ?? '';
$blockedIps        = $blockedIps ?? [];
$vaultLat          = $vaultLat ?? '';
$vaultLng          = $vaultLng ?? '';
$vaultRadius       = $vaultRadius ?? '100';

$hasCoordinate = trim($vaultLat) !== '' && trim($vaultLng) !== '';
?>
?>
<div class="page-header">
    <h1 class="page-title"><?= icon('lock', 28) ?> Cofre</h1>
    <p class="page-subtitle">Configure o cofre da gincana: código, regras de bloqueio e link público.</p>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     CARD 1 — CÓDIGO DO COFRE
     ═══════════════════════════════════════════════════════════════ -->
<form method="post" action="/cofre/config" class="settings-form" data-validate>
    <?= csrf_field() ?>

    <div class="settings-card">
        <h2 class="settings-card-title">
            <?= icon('lock', 20) ?>
            Código do cofre
        </h2>

        <p class="form-help-text" style="margin-top: 0; margin-bottom: 18px;">
            Este é o código de <strong>9 dígitos</strong> que a equipe encontra no mundo físico.
            Ao digitá-lo na página pública do cofre, ela revela a <strong>senha do desafio final</strong>.
        </p>

        <div class="form-group">
            <label for="vaultCode">Código do cofre (9 dígitos)</label>
            <div class="password-field">
                <input type="password" id="vaultCode" name="vaultCode" class="form-input"
                       maxlength="9" inputmode="numeric" pattern="[0-9]*"
                       value="<?= e($vaultCode) ?>" placeholder="Ex.: 123456789"
                       autocomplete="off">
                <button type="button" class="password-toggle" data-toggle-password="vaultCode"
                        aria-label="Mostrar código" title="Mostrar/ocultar código">
                    <?= icon('visibility', 20, 'icon-eye-open') ?>
                    <?= icon('visibility_off', 20, 'icon-eye-closed', 'style="display:none;"') ?>
                </button>
            </div>
            <div class="error-inline"></div>
            <p class="form-help-text">Deixe vazio para desativar o cofre.</p>
        </div>

        <div class="form-group">
            <label for="vaultMaxAttempts">Tentativas antes de bloquear</label>
            <input type="number" id="vaultMaxAttempts" name="vaultMaxAttempts" class="form-input"
                   min="1" max="20" step="1" value="<?= e($vaultMaxAttempts) ?>">
            <div class="error-inline"></div>
            <p class="form-help-text">Número de tentativas erradas permitidas antes de bloquear o cofre (1 a 20).</p>
        </div>

        <div class="form-group">
            <label for="vaultBlockMinutes">Tempo de bloqueio (minutos)</label>
            <input type="number" id="vaultBlockMinutes" name="vaultBlockMinutes" class="form-input"
                   min="1" max="1440" step="1" value="<?= e($vaultBlockMinutes) ?>">
            <div class="error-inline"></div>
            <p class="form-help-text">Minutos que o cofre fica bloqueado após exceder as tentativas (1 a 1440 = 24h).</p>
        </div>

        <div class="toggle-group">
            <div>
                <div class="toggle-label-text">Bloquear até o dia seguinte</div>
                <div class="toggle-label-desc">Após 3 bloqueios seguidos, o cofre só libera no dia seguinte.</div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="vaultBlockNextDay" value="1"
                       <?= $vaultBlockNextDay === '1' ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="form-group" style="margin-top: 8px;">
            <label for="vaultRadius">Raio permitido (metros)</label>
            <input type="number" id="vaultRadius" name="vaultRadius" class="form-input"
                   min="10" max="5000" step="1" value="<?= e($vaultRadius) ?>">
            <div class="error-inline"></div>
            <p class="form-help-text">
                Distância máxima (em metros) que o visitante pode estar do cofre para abrir a página.
                Valores entre 10 e 5.000. Padrão: 100 m.
            </p>
        </div>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">
            <?= icon('save', 18) ?>
            Salvar configurações do cofre
        </button>
    </div>
</form>

<!-- ═══════════════════════════════════════════════════════════════
     CARD 2 — LOCAL DO COFRE (geofence)
     ═══════════════════════════════════════════════════════════════ -->
<div class="settings-card" style="animation-delay: 0.1s;">
    <h2 class="settings-card-title">
        <?= icon('place', 20) ?>
        Local do cofre
    </h2>

    <?php if ($hasCoordinate): ?>
        <p class="form-help-text" style="margin-top: 0; margin-bottom: 14px;">
            O cofre só abre num raio do local abaixo. A coordenada é capturada
            pelo <strong>app do admin</strong> (aba Cofre → botão <em>Capturar coordenada</em>).
        </p>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
            <code style="
                font-family: 'Courier New', monospace;
                font-size: 0.92rem;
                color: #F97316;
                background: rgba(249,115,22,0.08);
                padding: 8px 14px;
                border-radius: 8px;
                border: 1px solid rgba(249,115,22,0.2);
                letter-spacing: 0.02em;
            "><?= e($vaultLat) ?>, <?= e($vaultLng) ?></code>

            <a href="https://www.google.com/maps?q=<?= e($vaultLat) ?>,<?= e($vaultLng) ?>"
               target="_blank" rel="noopener"
               class="btn btn-sm btn-auto"
               style="background: rgba(249,115,22,0.12); border: 1px solid rgba(249,115,22,0.3); color: #F97316; text-decoration: none;">
                <?= icon('open_in_new', 16) ?>
                Abrir no mapa
            </a>
        </div>

        <!-- Limpar coordenada -->
        <form method="post" action="/cofre/config"
              data-confirm-modal="Limpar a coordenada do cofre? O cofre passará a abrir em qualquer lugar.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear-coordinate">
            <button type="submit" class="btn btn-sm btn-auto"
                    style="background: rgba(192,57,43,0.12); border: 1px solid rgba(192,57,43,0.3); color: #f5a6a0;">
                <?= icon('delete', 16) ?>
                Limpar coordenada
            </button>
        </form>

    <?php else: ?>

        <div style="
            padding: 16px;
            background: rgba(249,115,22,0.06);
            border: 1px dashed rgba(249,115,22,0.25);
            border-radius: 10px;
            margin-bottom: 4px;
        ">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <?= icon('info', 18, '', 'style="color:#F97316;"') ?>
                <span style="color: rgba(247,236,212,0.75); font-size: 0.9rem; font-weight: 600;">
                    Nenhuma coordenada configurada — o cofre abre em qualquer lugar.
                </span>
            </div>
            <p class="form-help-text" style="margin: 0;">
                A coordenada é capturada pelo <strong>app do admin</strong> (aba Cofre → botão <em>Capturar coordenada</em>).
            </p>
        </div>

    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     CARD 3 — LINK PÚBLICO DO COFRE
     ═══════════════════════════════════════════════════════════════ -->
<div class="settings-card" style="animation-delay: 0.15s;">
    <h2 class="settings-card-title">
        <?= icon('link', 20) ?>
        Link público do cofre
    </h2>

    <p class="form-help-text" style="margin-top: 0; margin-bottom: 14px;">
        Compartilhe este link com as equipes. A página é pública — não precisa de login.
        Quando o código estiver correto, a senha do desafio final
        (<code style="color:#F97316;"><?= e($finalAnswer) ?></code>)
        será revelada na tela.
    </p>

    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <div class="api-url-box" style="margin-bottom: 10px;">
                <span class="url-text"><?= e($vaultUrl) ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-copy" data-copy="<?= e($vaultUrl) ?>" style="margin-bottom: 8px;">
                <?= icon('content_copy', 14) ?> Copiar link
            </button>
        </div>

        <div style="text-align: center; flex-shrink: 0;">
            <div style="background: #fff; border-radius: 10px; padding: 8px; display: inline-block; border: 1px solid rgba(249,115,22,0.15);">
                <img src="/cofre/qr.svg" alt="QR code do cofre" width="180" height="180">
            </div>
            <div style="font-size: 0.72rem; color: rgba(247,236,212,0.4); margin-top: 6px;">QR Code do cofre</div>
        </div>
    </div>

    <div style="margin-top: 16px;">
        <a href="/cofre" target="_blank" rel="noopener" class="btn btn-primary btn-auto btn-sm" style="background:linear-gradient(135deg,#22C55E,#168a3a);">
            <?= icon('open_in_new', 16) ?>
            Abrir a página do cofre
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     CARD 4 — BLOQUEIOS ATIVOS
     ═══════════════════════════════════════════════════════════════ -->
<div class="settings-card" style="animation-delay: 0.25s;">
    <h2 class="settings-card-title">
        <?= icon('block', 20) ?>
        Bloqueios ativos
    </h2>

    <?php if (empty($blockedIps)): ?>
        <div style="display:flex; align-items:center; gap:10px; padding:8px 0; color:#22C55E; font-size:0.9rem;">
            <?= icon('check_circle', 20) ?>
            <span>Nenhum bloqueio ativo.</span>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(247,236,212,0.12);">
                        <th style="text-align:left; padding:8px 10px; color:rgba(247,236,212,0.6); font-weight:600;">IP</th>
                        <th style="text-align:left; padding:8px 10px; color:rgba(247,236,212,0.6); font-weight:600;">Bloqueado até</th>
                        <th style="text-align:center; padding:8px 10px; color:rgba(247,236,212,0.6); font-weight:600;">Bloqueios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blockedIps as $ip): ?>
                        <tr style="border-bottom:1px solid rgba(247,236,212,0.06);">
                            <td style="padding:8px 10px; color:#f7ecd4; font-family:monospace;"><?= e($ip['ip']) ?></td>
                            <td style="padding:8px 10px; color:rgba(247,236,212,0.75);">
                                <?= e(date('d/m/Y H:i', strtotime($ip['blocked_until']))) ?>
                            </td>
                            <td style="padding:8px 10px; text-align:center; color:rgba(247,236,212,0.75);">
                                <?= (int) $ip['blocks'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="form-help-text" style="margin-top:12px;">
        Quem extrapolar as tentativas fica bloqueado por um tempo.
        Você pode liberar todo mundo aqui.
    </p>

    <?php if (!empty($blockedIps)): ?>
        <form method="post" action="/cofre/config"
              data-confirm-modal="Liberar todos os bloqueios? As equipes que estavam travadas poderão tentar novamente.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unblock">
            <button type="submit" class="btn btn-primary btn-auto btn-sm" style="background:linear-gradient(135deg,#F97316,#EA580C);">
                <?= icon('lock_open', 18) ?>
                Liberar todos os bloqueios
            </button>
        </form>
    <?php endif; ?>
</div>
