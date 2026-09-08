<?php

/**
 * Formulário de cadastro/edição de tesouro.
 *
 * @var string                     $title    Título da página
 * @var string                     $action   Action do formulário
 * @var array<string, mixed>|null  $treasure Tesouro sendo editado (null ao cadastrar)
 * @var array<string, string>      $old      Valores digitados após erro de validação
 * @var array<int, string>         $errors   Mensagens de erro
 */

$title = $title ?? 'Tesouro';
$action = $action ?? '/tesouros/novo';
$treasure = $treasure ?? null;
$old = $old ?? [];
$errors = $errors ?? [];

/**
 * Prioridade: valores enviados ($old) > valores do banco ($treasure).
 */
$value = static function (string $key, string $default = '') use ($old, $treasure): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }

    if (is_array($treasure) && array_key_exists($key, $treasure)) {
        return (string) $treasure[$key];
    }

    return $default;
};

$isEdit = is_array($treasure) && ($treasure['id'] ?? null) !== null;

// Coordinate status
$lat = $isEdit ? ($treasure['lat'] ?? null) : null;
$lng = $isEdit ? ($treasure['lng'] ?? null) : null;
$hasGps = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';

// QR status
$qrPath = $isEdit ? (string) ($treasure['qr_svg_path'] ?? '') : '';
$hasQr = $qrPath !== '';
?>
<div class="page-header">
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="page-subtitle">Defina o código, a descrição, a dica e as duas charadas do tesouro.</p>
</div>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e((string) $error) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e($action) ?>" class="settings-form" data-validate>
    <?= csrf_field() ?>

    <!-- Card: Identificação -->
    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="3" y1="9" x2="21" y2="9"/>
                <line x1="9" y1="21" x2="9" y2="9"/>
            </svg>
            Identificação
        </h2>

        <div class="form-group">
            <label for="code">Código *</label>
            <input type="text" id="code" name="code" class="form-input" maxlength="50"
                   value="<?= e($value('code')) ?>" placeholder="Ex.: T11" required>
            <div class="error-inline"></div>
            <p class="form-help-text">Identificador único (2 a 50 caracteres). Ex.: T01, T02...</p>
        </div>

        <div class="form-group">
            <label for="name">Nome do tesouro *</label>
            <input type="text" id="name" name="name" class="form-input" maxlength="190"
                   value="<?= e($value('name')) ?>" required>
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="description">Descrição *</label>
            <textarea id="description" name="description" class="form-input" rows="3" required><?= e($value('description')) ?></textarea>
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="clue">Dica para o próximo local *</label>
            <textarea id="clue" name="clue" class="form-input" rows="2" required><?= e($value('clue')) ?></textarea>
            <div class="error-inline"></div>
            <p class="form-help-text">Entregue à equipe quando ela acertar a charada deste tesouro.</p>
        </div>
    </div>

    <!-- Card: Charada 1 -->
    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 17 10 11 4 5"/>
                <line x1="12" y1="19" x2="20" y2="19"/>
            </svg>
            Charada 1
        </h2>

        <div class="form-group">
            <label for="riddle1">Pergunta da charada 1 *</label>
            <textarea id="riddle1" name="riddle1" class="form-input" rows="3" required><?= e($value('riddle1')) ?></textarea>
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="answer1">Resposta da charada 1 *</label>
            <!-- Digit boxes for answer1 -->
            <div class="digit-input-wrapper" data-digit-input data-hidden-name="answer1">
                <div class="digit-input-row">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 1 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 2 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 3 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 4 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 5 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 6 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 7 da resposta 1">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 8 da resposta 1">
                </div>
                <input type="hidden" name="answer1" value="<?= e($value('answer1')) ?>">
            </div>
            <div class="error-inline"></div>
            <p class="form-help-text">Somente dígitos, de 4 a 8.</p>
        </div>
    </div>

    <!-- Card: Charada 2 -->
    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 17 10 11 4 5"/>
                <line x1="12" y1="19" x2="20" y2="19"/>
            </svg>
            Charada 2
        </h2>

        <div class="form-group">
            <label for="riddle2">Pergunta da charada 2 *</label>
            <textarea id="riddle2" name="riddle2" class="form-input" rows="3" required><?= e($value('riddle2')) ?></textarea>
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="answer2">Resposta da charada 2 *</label>
            <!-- Digit boxes for answer2 -->
            <div class="digit-input-wrapper" data-digit-input data-hidden-name="answer2">
                <div class="digit-input-row">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 1 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 2 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 3 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 4 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 5 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 6 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 7 da resposta 2">
                    <input type="text" class="digit-box" maxlength="1" inputmode="numeric" pattern="[0-9]" placeholder="·" aria-label="Dígito 8 da resposta 2">
                </div>
                <input type="hidden" name="answer2" value="<?= e($value('answer2')) ?>">
            </div>
            <div class="error-inline"></div>
            <p class="form-help-text">Somente dígitos, de 4 a 8. Cada equipe recebe uma das duas charadas.</p>
        </div>
    </div>

    <!-- Card: QR code (somente leitura) -->
    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            QR code
        </h2>

        <?php if ($isEdit && $hasQr): ?>
            <div class="qr-current">
                <img
                    src="<?= e($qrPath) ?>"
                    alt="QR code atual"
                    class="qr-thumb qr-thumb-preview"
                >
                <div>
                    <p style="margin: 0; font-size: 13px; opacity: .7;">Código gerado automaticamente:</p>
                    <code class="qr-code-read-only"><?= e((string) ($treasure['qr_content'] ?? '')) ?></code>
                    <div style="margin-top: 10px;">
                        <a href="<?= e($qrPath) ?>"
                           download="QR_<?= e((string) ($treasure['code'] ?? 'tesouro')) ?>.svg"
                           class="download-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Baixar QR (SVG)
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="qr-thumb qr-thumb-placeholder" style="margin-bottom: 12px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
            </div>
            <p style="margin: 0; font-size: 14px; opacity: .85;">O código do QR será gerado automaticamente ao salvar.</p>
        <?php endif; ?>

        <!-- Coordinate status (edit only) -->
        <?php if ($isEdit): ?>
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(245, 197, 66, 0.08);">
                <?php if ($hasGps): ?>
                    <span class="badge badge-success">📍 Coordenada confirmada (<?= e((string) $lat) ?>, <?= e((string) $lng) ?>)</span>
                <?php else: ?>
                    <span class="badge badge-warning">📍 Coordenada pendente — confirme pelo app admin</span>
                <?php endif; ?>

                <?php if ((int) ($treasure['active'] ?? 0) === 1): ?>
                    <span class="badge badge-success" style="margin-left: 6px;">✔ Ativo</span>
                <?php else: ?>
                    <span class="badge badge-warning" style="margin-left: 6px;">Inativo</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="settings-actions">
        <a href="/tesouros" class="btn btn-ghost" style="width: auto;">Cancelar</a>
        <button type="submit" class="btn btn-primary" style="width: auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
                <polyline points="7 3 7 8 15 8"/>
            </svg>
            Salvar tesouro
        </button>
    </div>
</form>
