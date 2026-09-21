<?php

/**
 * Página Desafio Final — pista, senha e pontuações finais.
 *
 * @var string $finalClue          Dica do desafio final
 * @var string $finalAnswer        Senha final (valor atual salvo — visível/legível)
 * @var string $finalCorrectPoints Pontos ao acertar o desafio final
 * @var string $finalWrongPenalty  Pontos perdidos por erro
 * @var string $vaultCode          Código do cofre (9 dígitos ou vazio)
 * @var string $vaultMaxAttempts   Tentativas antes de bloquear
 * @var string $vaultBlockMinutes  Minutos de bloqueio
 * @var string $vaultBlockNextDay  Bloquear até o dia seguinte (1/0)
 */

$finalClue = $finalClue ?? '';
$finalAnswer = $finalAnswer ?? '';
$finalCorrectPoints = $finalCorrectPoints ?? '100';
$finalWrongPenalty = $finalWrongPenalty ?? '20';
$vaultCode = $vaultCode ?? '';
$vaultMaxAttempts = $vaultMaxAttempts ?? '3';
$vaultBlockMinutes = $vaultBlockMinutes ?? '5';
$vaultBlockNextDay = $vaultBlockNextDay ?? '0';

// URL pública do cofre (para link e QR)
$vaultUrl = rtrim((string) app_config('app.url', ''), '/') . '/cofre';

// Gerar QR code SVG do link do cofre
$vaultQrSvg = '';
if ($vaultUrl !== '' && $vaultUrl !== '/cofre') {
    try {
        $qrOptions = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_M,
            'scale'        => 5,
            'addQuietzone' => true,
            'imageBase64'  => false,
        ]);
        $vaultQrSvg = (new \chillerlan\QRCode\QRCode($qrOptions))->render($vaultUrl);
    } catch (\Throwable $e) {
        error_log('QR do cofre: ' . $e->getMessage());
    }
}
?>
<div class="page-header">
    <h1 class="page-title">Desafio Final</h1>
    <p class="page-subtitle">A dica, a senha e a pontuação que encerram a caça ao tesouro.</p>
</div>

<form method="post" action="/desafio-final" class="settings-form" id="finalChallengeForm">
    <?= csrf_field() ?>

    <div class="settings-card">
        <h2 class="settings-card-title">
            <?= icon('check_circle', 20) ?>
            Desafio final
        </h2>

        <div class="form-group">
            <label for="finalClue">Primeira dica do desafio final</label>
            <textarea id="finalClue" name="finalClue" class="form-input" rows="3"
                      placeholder="Ex.: Vá até o colégio e abra o cofre."><?= e($finalClue) ?></textarea>
            <div class="error-inline"></div>
            <p class="form-help-text">Mostrada ao aplicativo quando a equipe termina todos os tesouros.</p>
        </div>

        <div class="form-group">
            <label for="finalAnswer">Resposta/senha final *</label>
            <input type="text" id="finalAnswer" name="finalAnswer" class="form-input" maxlength="64"
                   value="<?= e($finalAnswer) ?>" placeholder="Ex.: 123ABC#"
                   style="margin-top: 8px;">
            <div class="error-inline"></div>
            <p class="form-help-text">Palavra, frase ou símbolos (máx. 64 caracteres). Comparação sem diferenciar maiúsculas/minúsculas.</p>
        </div>

        <div class="form-group">
            <label for="finalCorrectPoints">Pontos ao acertar o desafio final</label>
            <input type="number" id="finalCorrectPoints" name="finalCorrectPoints" class="form-input"
                   min="1" max="1000" step="1" value="<?= e($finalCorrectPoints) ?>">
            <div class="error-inline"></div>
            <p class="form-help-text">Pontos ganhos pela equipe ao acertar o desafio final (1 a 1000).</p>
        </div>

        <div class="form-group">
            <label for="finalWrongPenalty">Pontos perdidos por erro</label>
            <input type="number" id="finalWrongPenalty" name="finalWrongPenalty" class="form-input"
                   min="0" max="1000" step="1" value="<?= e($finalWrongPenalty) ?>">
            <div class="error-inline"></div>
            <p class="form-help-text">Pontos descontados da equipe a cada tentativa errada do desafio final (0 a 1000).</p>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         COFRE DA GINCANA
         ═══════════════════════════════════════════════════════════════ -->
    <div class="settings-card" style="animation-delay: 0.25s;">
        <h2 class="settings-card-title">
            <?= icon('lock', 20) ?>
            Cofre da gincana
        </h2>

        <div class="form-group">
            <label for="vaultCode">Código do cofre (9 dígitos)</label>
            <div class="password-field">
                <input type="password" id="vaultCode" name="vaultCode" class="form-input"
                       maxlength="9" inputmode="numeric" pattern="[0-9]*"
                       value="<?= e($vaultCode) ?>" placeholder="Ex.: 123456789"
                       autocomplete="off">
                <button type="button" class="password-toggle" data-toggle-password="vaultCode"
                        aria-label="Mostrar código" title="Mostrar/ocultar código">
                    <span class="material-icons icon-eye-open">visibility</span>
                    <span class="material-icons icon-eye-closed" style="display:none;">visibility_off</span>
                </button>
            </div>
            <div class="error-inline"></div>
            <p class="form-help-text">Este é o código que a equipe encontra no mundo físico. Deixe vazio para desativar o cofre.</p>
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
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         LINK + QR CODE DO COFRE
         ═══════════════════════════════════════════════════════════════ -->
    <?php if ($vaultUrl !== '/cofre'): ?>
    <div class="settings-card" style="animation-delay: 0.35s;">
        <h2 class="settings-card-title">
            <?= icon('link', 20) ?>
            Link público do cofre
        </h2>

        <p class="form-help-text" style="margin-bottom: 14px; margin-top: 0;">
            Compartilhe este link com as equipes. A página é pública — não precisa de login.
            Quando o código estiver correto, a <strong>senha do desafio final</strong> (<code style="color:#F97316;"><?= e($finalAnswer) ?></code>) será revelada na tela.
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

            <?php if ($vaultQrSvg !== ''): ?>
            <div style="text-align: center; flex-shrink: 0;">
                <div style="background: #fff; border-radius: 10px; padding: 8px; display: inline-block; border: 1px solid rgba(249,115,22,0.15);">
                    <?= $vaultQrSvg ?>
                </div>
                <div style="font-size: 0.72rem; color: rgba(247,236,212,0.4); margin-top: 6px;">QR Code do cofre</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar desafio final</button>
    </div>
</form>
