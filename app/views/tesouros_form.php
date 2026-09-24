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
<a href="/tesouros" class="treasure-back-link">← Voltar para tesouros</a>
<div class="page-header">
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="page-subtitle">Defina o código, o local do tesouro, a dica e as duas charadas.</p>
</div>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e((string) $error) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e($action) ?>" class="settings-form settings-form--wide" data-validate>
    <?= csrf_field() ?>

    <div class="treasure-form-layout">
        <div class="treasure-form-main">
            <!-- Card: Identificação -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <?= icon('badge', 20) ?>
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
                    <label>Nome do tesouro</label>
                    <input type="text" class="form-input" disabled
                           value="<?= e($isEdit ? (string) ($treasure['name'] ?? '') : 'Tesouro ' . (\App\Repositories\TreasureRepository::nextSortOrder())) ?>">
                    <div class="error-inline"></div>
                    <p class="form-help-text">
                        O nome é <strong>automático</strong>: sempre <strong>"Tesouro N"</strong>,
                        onde N é a posição do tesouro na lista. Para mudar a ordem,
                        arraste os tesouros na listagem.
                    </p>
                </div>

                <div class="form-group">
                    <label for="description">Local do tesouro *</label>
                    <textarea id="description" name="description" class="form-input" rows="3" required><?= e($value('description')) ?></textarea>
                    <div class="error-inline"></div>
                    <p class="form-help-text">
                        Escreva aqui o <strong>local real</strong> (ex.: "Biblioteca, atrás da estante de revistas").
                        <strong style="color:#22C55E;">Somente o admin vê este campo</strong> — as equipes
                        <strong>não têm acesso</strong> a ele em nenhum momento (nem no app).
                    </p>
                </div>

                <div class="form-group">
                    <label for="clue">Dica para o próximo local *</label>
                    <textarea id="clue" name="clue" class="form-input" rows="2" required><?= e($value('clue')) ?></textarea>
                    <div class="error-inline"></div>
                    <p class="form-help-text">Entregue à equipe quando ela acertar a charada deste tesouro.</p>
                </div>

                <!-- Companhia dos responsáveis -->
                <div class="form-group" style="margin-top:8px;">
                    <div class="toggle-group" style="border:1px solid rgba(249,115,22,0.25);border-radius:12px;padding:16px;background:rgba(249,115,22,0.05);">
                        <div>
                            <div class="toggle-label-text">
                                👨‍👩‍👧 Este tesouro deve ser encontrado na companhia dos responsáveis
                            </div>
                            <div class="toggle-label-desc">
                                O app mostra um <strong>aviso bem visível</strong> na tela do tesouro e na
                                selfie, e na selfie explica que <strong>pelo menos um responsável precisa
                                aparecer na foto</strong> — sob risco de <strong>desclassificar o tesouro</strong>.
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="with_guardian" value="1"
                                   <?= ((int) ($treasure['with_guardian'] ?? 0) === 1 || ($value('with_guardian') === '1')) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card: Charada 1 -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <?= icon('terminal', 20) ?>
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
                    <p class="form-help-text">Somente dígitos, de 1 a 8.</p>
                </div>
            </div>

            <!-- Card: Charada 2 -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <?= icon('terminal', 20) ?>
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
                    <p class="form-help-text">Somente dígitos, de 1 a 8. Cada equipe recebe uma das duas charadas.</p>
                </div>
            </div>
        </div>

        <aside class="treasure-form-aside">
            <div class="treasure-aside-card">
                <!-- QR code (somente leitura) -->
                <h2 class="settings-card-title">
                    <?= icon('qr_code_2', 20) ?>
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
                                    <?= icon('download', 14) ?>
                                    Baixar QR (SVG)
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="qr-thumb qr-thumb-placeholder" style="margin-bottom: 12px;">
                        <?= icon('qr_code_2', 36) ?>
                    </div>
                    <p style="margin: 0; font-size: 14px; opacity: .85;">O código do QR será gerado automaticamente ao salvar.</p>
                <?php endif; ?>

                <!-- Status das equipes (edit only) -->
                <?php if ($isEdit): ?>
                    <div class="aside-team-status-block">
                        <p class="aside-section-label">Status das equipes</p>
                        <div class="aside-team-progress">
                            <?php foreach (['preta', 'laranja'] as $color): ?>
                                <?php
                                $found = false;
                                $foundAt = '';
                                $row = ($progress ?? [])[$color] ?? null;
                                if ($row !== null && (int) ($row['riddle_correct'] ?? 0) === 1) {
                                    $found = true;
                                    $foundAt = (string) ($row['found_at'] ?? '');
                                }
                                ?>
                                <span class="team-marker marker-<?= e($color) ?> <?= $found ? 'marker-found' : 'marker-missing' ?>">
                                    <span class="team-marker-dot"></span>
                                    <span class="team-marker-label"><?= e($color) ?></span>
                                    <?php if ($found): ?>
                                        <span class="team-marker-status"><?= icon('check', 14) ?> <?= e($foundAt) ?></span>
                                    <?php else: ?>
                                        <span class="team-marker-status">Não encontrado</span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Coordinate status + badges (edit only) -->
                <?php if ($isEdit): ?>
                    <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(245, 197, 66, 0.08); display:flex; align-items:flex-start; gap:8px; flex-wrap:wrap;">
                        <?php if ($hasGps): ?>
                            <div class="coord-status">
                                <span class="badge badge-success"><?= icon('place', 14) ?> Coordenada confirmada</span>
                                <div class="coord-values">
                                    <div class="coord-value"><span class="coord-value-label">Lat</span><span><?= e((string) $lat) ?></span></div>
                                    <div class="coord-value"><span class="coord-value-label">Lng</span><span><?= e((string) $lng) ?></span></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="badge badge-warning"><?= icon('place', 14) ?> Coordenada pendente</span>
                        <?php endif; ?>

                        <?php if ((int) ($treasure['active'] ?? 0) === 1): ?>
                            <span class="badge badge-success"><?= icon('check', 14) ?> Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Inativo</span>
                        <?php endif; ?>

                        <?php if ($hasGps): ?>
                            <button type="submit" form="clearCoordsForm"
                                    data-confirm-modal="Zerar a coordenada deste tesouro?&#10;Ele será DESATIVADO e precisará ser confirmado novamente pelo app admin no local."
                                    class="btn btn-sm btn-auto"
                                    style="width:auto; background:linear-gradient(135deg,#c0392b,#8e2a20); color:#fff; padding:6px 12px; font-size:.78rem;">
                                <?= icon('delete', 14) ?>
                                Zerar coordenada
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Salvar -->
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <?= icon('save', 18) ?>
                        Salvar
                    </button>
                </div>
            </div>
        </aside>
    </div>
</form>

<?php if ($isEdit): ?>
<!-- Form separado: zera a coordenada e desativa o tesouro (o botão na
     seção de coordenadas o aciona via atributo form=) -->
<form id="clearCoordsForm" method="post" action="/tesouros/<?= (int) ($treasure['id'] ?? 0) ?>/coordenada/zerar" style="display:none;">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
