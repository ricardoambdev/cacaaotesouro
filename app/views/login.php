<?php

/**
 * Página de login.
 *
 * @var string|null          $error Mensagem de erro direta (normalmente null)
 * @var array{username?:string} $old   Valores preenchidos anteriormente
 */

$error = $error ?? null;
$old = $old ?? ['username' => ''];
$username = (string) ($old['username'] ?? '');
?>
<div class="auth-card">
    <h1 class="auth-card-title">Entrar</h1>
    <p class="auth-card-subtitle">Acesse sua conta para continuar a aventura</p>

    <?php if ($error !== null && $error !== ''): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" data-validate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" class="form-input" value="<?= e($username) ?>" autocomplete="username" placeholder="Digite seu usuário" required>
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" class="form-input" autocomplete="current-password" placeholder="Sua senha" required>
            <div class="error-inline"></div>
        </div>

        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>

    <div class="auth-links">
        <p><a href="/recuperar">Esqueci minha senha</a></p>
        <p>Ainda não tem conta? <a href="/register">Cadastre-se</a></p>
    </div>
</div>
