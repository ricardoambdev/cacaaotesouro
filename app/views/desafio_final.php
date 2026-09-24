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

        <?php if ($finalBlocked): ?>
            <div class="form-group" style="border:1px solid rgba(239,68,68,0.35); background:rgba(239,68,68,0.06); border-radius:12px; padding:14px;">
        <?php else: ?>
            <div class="form-group" style="border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.05); border-radius:12px; padding:14px;">
        <?php endif; ?>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; margin:0;">
                    <!-- Envia 0 quando o checkbox está desmarcado (padrão HTML). -->
                    <input type="hidden" name="finalBlocked" value="0">
                    <input type="checkbox" name="finalBlocked" value="1" <?= $finalBlocked ? 'checked' : '' ?>
                           style="width:18px; height:18px; accent-color:#EF4444;">
                    <span style="font-weight:700; color:#f7ecd4;">
                        Bloquear o Desafio Final (e o Cofre)
                    </span>
                </label>
                <p class="form-help-text" style="margin:10px 0 0;">
                    <?php if ($finalBlocked): ?>
                        🚫 <strong style="color:#EF4444;">Bloqueado agora.</strong>
                        O Cofre não abre e o app mostra "Aguardando a liberação do Desafio Final".
                        Desmarque e salve para liberar.
                    <?php else: ?>
                        ✅ <strong style="color:#22C55E;">Liberado agora.</strong>
                        Marque e salve para bloquear o Cofre e colocar as equipes em espera.
                    <?php endif; ?>
                </p>
                <p class="form-help-text" style="margin:8px 0 0;">
                    <?= icon('info', 13) ?>
                    Pode bloquear a qualquer momento — inclusive <strong>antes</strong> de alguém
                    encontrar o último tesouro, para o desafio já estar bloqueado quando chegarem.
                </p>
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
