<?php

/**
 * Página de criação de conta.
 *
 * @var string|null                 $error Mensagem de erro direta (normalmente null)
 * @var array{name?:string, username?:string} $old   Valores preenchidos anteriormente
 */

$error = $error ?? null;
$old = $old ?? ['name' => '', 'username' => ''];
$name = (string) ($old['name'] ?? '');
$username = (string) ($old['username'] ?? '');
?>
<div class="auth-card">
    <h1 class="auth-card-title">Criar conta</h1>
    <p class="auth-card-subtitle">Junte-se à aventura e comece a caçar</p>

    <?php if ($error !== null && $error !== ''): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register" data-validate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" class="form-input" value="<?= e($name) ?>" autocomplete="name" placeholder="Seu nome" required minlength="2">
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" class="form-input" value="<?= e($username) ?>" autocomplete="username" placeholder="Escolha um nome de usuário" required minlength="3" pattern="[a-zA-Z0-9_]{3,}" title="Apenas letras, números e underscores, mínimo 3 caracteres">
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" class="form-input" autocomplete="new-password" placeholder="Mínimo 6 caracteres" required minlength="6">
            <div class="error-inline"></div>
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirmar senha</label>
            <input type="password" id="password_confirm" name="password_confirm" class="form-input" autocomplete="new-password" placeholder="Repita a senha" required minlength="6">
            <div class="error-inline"></div>
        </div>

        <button type="submit" class="btn btn-primary">Criar conta</button>
    </form>

    <div class="auth-links">
        <p>Já tem conta? <a href="/login">Entrar</a></p>
    </div>
</div>
