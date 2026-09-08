<?php

/**
 * Página de solicitação de recuperação de senha.
 *
 * @var string|null          $error   Mensagem de erro direta (normalmente null)
 * @var array{username?:string} $old     Valores preenchidos anteriormente
 * @var string|null          $devLink Link de recuperação exibido apenas em dev
 */

$error = $error ?? null;
$old = $old ?? ['username' => ''];
$devLink = $devLink ?? null;
$username = (string) ($old['username'] ?? '');
?>
<div class="auth-card">
    <h1 class="auth-card-title">Recuperar senha</h1>
    <p class="auth-card-subtitle">Digite seu usuário para recuperar a senha</p>

    <?php if ($error !== null && $error !== ''): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($devLink !== null && $devLink !== ''): ?>
        <div class="dev-link">
            <strong>🗺️ Ambiente de desenvolvimento</strong>
            <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
        </div>
    <?php endif; ?>

    <form method="post" action="/recuperar" data-validate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" class="form-input" value="<?= e($username) ?>" autocomplete="username" placeholder="Digite seu usuário" required>
            <div class="error-inline"></div>
        </div>

        <button type="submit" class="btn btn-primary">Enviar link de recuperação</button>
    </form>

    <div class="auth-links">
        <p><a href="/login">Voltar ao login</a></p>
    </div>
</div>
