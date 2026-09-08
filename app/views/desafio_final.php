<?php

/**
 * Página Desafio Final — pista e senha finais.
 *
 * @var string $finalClue   Dica do desafio final
 * @var string $finalAnswer Senha final (valor atual salvo — NÃO exibido)
 */

$finalClue = $finalClue ?? '';
$finalAnswer = $finalAnswer ?? '';
$hasAnswer = $finalAnswer !== '';
?>
<div class="page-header">
    <h1 class="page-title">Desafio Final</h1>
    <p class="page-subtitle">A dica e a senha que encerram a caça ao tesouro (+100 pontos).</p>
</div>

<form method="post" action="/desafio-final" class="settings-form" id="finalChallengeForm">
    <?= csrf_field() ?>

    <!-- Keep actual value hidden so backend can compare/update -->
    <input type="hidden" id="finalAnswerHidden" name="_currentAnswer" value="<?= e($finalAnswer) ?>">

    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
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

            <?php if ($hasAnswer): ?>
                <!-- Show masked status when answer exists -->
                <div class="final-answer-masked">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Senha atual definida — digite abaixo para alterar
                </div>
            <?php endif; ?>

            <input type="text" id="finalAnswer" name="finalAnswer" class="form-input" maxlength="64"
                   value="" placeholder="<?= $hasAnswer ? '•••• — deixe em branco para manter' : 'Ex.: 123ABC#' ?>" required
                   style="margin-top: 8px;">
            <div class="error-inline"></div>
            <p class="form-help-text">Palavra, frase ou símbolos (máx. 64 caracteres). Comparação sem diferenciar maiúsculas/minúsculas.</p>
        </div>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar desafio final</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('finalChallengeForm');
    var visibleInput = document.getElementById('finalAnswer');
    var hiddenInput = document.getElementById('finalAnswerHidden');
    if (!form || !visibleInput || !hiddenInput) return;

    form.addEventListener('submit', function () {
        // If user left the visible field empty, send the current value back
        if (!visibleInput.value.trim() && hiddenInput.value) {
            visibleInput.value = hiddenInput.value;
        }
    });
})();
</script>
