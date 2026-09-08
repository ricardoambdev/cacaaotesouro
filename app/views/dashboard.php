<?php

/**
 * Painel principal (vazio por enquanto).
 *
 * @var array{name:string}      $user   Usuário logado
 * @var array<string,string>    $config Configurações do sistema
 */

$user = $user ?? [];
$config = $config ?? [];
$name = (string) ($user['name'] ?? '');
?>
<h1 class="dashboard-greeting">Bem-vindo, <?= e($name) ?>!</h1>

<div class="empty-state">
    <!-- Baú de tesouro animado -->
    <img src="/assets/imagens/tesouro.gif" alt="Baú de tesouro" class="empty-state-icon">

    <h2 class="empty-state-title">O painel ainda está vazio</h2>
    <p class="empty-state-text">As funcionalidades do Caça ao Tesouro chegarão em breve. Fique de olho! 🧭</p>
    <a href="/configuracoes" class="btn btn-primary">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        Explorar Configurações
    </a>
</div>
