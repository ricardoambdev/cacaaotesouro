<?php

/**
 * Página Desafio Final — pista, senha e pontuações finais.
 *
 * @var string $finalClue          Dica do desafio final
 * @var string $finalAnswer        Senha final (valor atual salvo — visível/legível)
 * @var string $finalCorrectPoints Pontos ao acertar o desafio final
 * @var string $finalWrongPenalty  Pontos perdidos por erro
 */

$finalClue = $finalClue ?? '';
$finalAnswer = $finalAnswer ?? '';
$finalCorrectPoints = $finalCorrectPoints ?? '100';
$finalWrongPenalty = $finalWrongPenalty ?? '20';
?>
<div class="page-header">
    <h1 class="page-title">Desafio Final</h1>
    <p class="page-subtitle">A dica, a senha e a pontuação que encerram a caça ao tesouro.</p>
</div>

<form method="post" action="/desafio-final" class="settings-form" id="finalChallengeForm">
    <?= csrf_field() ?>

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

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar desafio final</button>
    </div>
</form>
