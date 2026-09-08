<?php

/**
 * Página de redefinição de senha.
 *
 * @var string $token      Token de recuperação (enviado via POST no form)
 * @var bool   $tokenValid Indica se o token ainda é válido (controla o form)
 */

$token = $token ?? '';
$tokenValid = (bool) ($tokenValid ?? false);
?>
<div class="auth-card">
    <h1 class="auth-card-title">Definir nova senha</h1>
    <p class="auth-card-subtitle">Escolha uma nova senha segura para sua conta</p>

    <?php if ($tokenValid): ?>
        <form method="post" action="/redefinir" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="password">Nova senha</label>
                <input type="password" id="password" name="password" class="form-input" autocomplete="new-password" placeholder="Mínimo 6 caracteres" required minlength="6">
                <div class="error-inline"></div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmar nova senha</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-input" autocomplete="new-password" placeholder="Repita a senha" required minlength="6">
                <div class="error-inline"></div>
            </div>

            <button type="submit" class="btn btn-primary">Salvar nova senha</button>
        </form>
    <?php else: ?>
        <div class="error">Este link de recuperação é inválido ou expirou.</div>
    <?php endif; ?>

    <div class="auth-links">
        <p><a href="/login">Voltar ao login</a></p>
    </div>
</div>
