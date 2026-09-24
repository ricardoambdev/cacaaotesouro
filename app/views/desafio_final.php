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
            <?= icon('info', 14) ?>
            <strong style="color:#22C55E;">Errar o desafio final NÃO tira pontos.</strong>
            A equipe pode tentar quantas vezes quiser — só o acerto dá pontos.
        </div>

        <p class="form-help-text" style="margin-top: 8px; padding-top: 12px; border-top: 1px solid rgba(247,236,212,0.08);">
            <?= icon('lock', 14) ?>
            A senha acima é revelada pelo Cofre da gincana.
            <a href="/cofre/config" style="color:#F97316; font-weight:600;">Configurar o Cofre</a>
        </p>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar desafio final</button>
    </div>
</form>
