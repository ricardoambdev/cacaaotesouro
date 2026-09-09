<?php

/**
 * Página de configurações do sistema.
 *
 * Prioridade de preenchimento: $old (valores enviados) > $config (valores
 * persistidos no banco).
 *
 * @var array<string,string>       $config Valores atuais do banco
 * @var array<string,string>       $old    Valores enviados após erro de validação
 * @var array<int,string>          $errors Lista de mensagens de erro
 * @var string                     $lanIp  IP LAN detectado
 * @var string                     $systemUrl URL do sistema em rede
 * @var string                     $apiUrl URL da API em rede
 * @var bool                       $hasApk Se há APK disponível
 * @var bool                       $devMode Modo desenvolvimento ativo
 * @var string                     $appUrl URL base do app
 */

$config  = $config ?? [];
$old     = $old ?? [];
$errors  = $errors ?? [];
$lanIp      = $lanIp ?? '';
$systemUrl  = $systemUrl ?? '';
$apiUrl     = $apiUrl ?? '';
$hasApk     = $hasApk ?? false;
$devMode    = $devMode ?? false;
$appUrl     = $appUrl ?? '';
$environments     = $environments ?? [];
$currentEnvironment = $currentEnvironment ?? 'servidor';

// Existing fields
$siteName          = (string) ($old['siteName'] ?? $config['siteName'] ?? '');
$description       = (string) ($old['description'] ?? $config['description'] ?? '');
$supportEmail      = (string) ($old['supportEmail'] ?? $config['supportEmail'] ?? '');
$soundEnabled      = (string) ($old['soundEnabled'] ?? $config['soundEnabled'] ?? '1');
$animationEnabled  = (string) ($old['animationEnabled'] ?? $config['animationEnabled'] ?? '1');
$itemsPerPage      = (string) ($old['itemsPerPage'] ?? $config['itemsPerPage'] ?? '10');

// New API fields
$apiBaseUrl = (string) ($old['apiBaseUrl'] ?? $config['apiBaseUrl'] ?? '');
$apiDevMode = (string) (isset($old['apiDevMode']) ? $old['apiDevMode'] : ($config['apiDevMode'] ?? '1'));

// Game fields
$treasureOrder = (string) ($old['treasureOrder'] ?? $config['treasureOrder'] ?? 'estabelecida');
$adminUsername = (string) ($old['adminUsername'] ?? $config['adminUsername'] ?? 'admin');

// Team credentials (backend may not have added $teams yet)
$teams = $teams ?? [];
$teamOrangeUsername = (string) ($old['teamOrangeUsername'] ?? ($teams['laranja']['username'] ?? ''));
$teamBlackUsername  = (string) ($old['teamBlackUsername']  ?? ($teams['preta']['username']  ?? ''));
// Passwords are pre-filled from $teams (backend passes password in plain text)
$teamOrangePassword = (string) ($teams['laranja']['password'] ?? '');
$teamBlackPassword  = (string) ($teams['preta']['password']  ?? '');
?>
<div class="page-header">
    <h1 class="page-title">Configurações</h1>
    <p class="page-subtitle">Personalize a aparência e o comportamento do sistema</p>
</div>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e((string) $error) ?></div>
<?php endforeach; ?>

<!-- Tab Navigation -->
<nav class="tab-nav" role="tablist" aria-label="Configurações">
    <button type="button" class="tab-btn active" data-tab="geral" role="tab" aria-selected="true" aria-controls="tab-geral">
        ⚙ Geral
    </button>
    <button type="button" class="tab-btn" data-tab="api" role="tab" aria-selected="false" aria-controls="tab-api">
        🔗 API &amp; Desenvolvimento
    </button>
    <button type="button" class="tab-btn" data-tab="equipes" role="tab" aria-selected="false" aria-controls="tab-equipes">
        👥 Usuários das Equipes
    </button>
    <button type="button" class="tab-btn" data-tab="jogo" role="tab" aria-selected="false" aria-controls="tab-jogo">
        🎮 Jogo
    </button>
    <button type="button" class="tab-btn" data-tab="limpeza" role="tab" aria-selected="false" aria-controls="tab-limpeza">
        🧹 Limpeza
    </button>
</nav>

<form method="post" action="/configuracoes" class="settings-form" data-validate>
    <?= csrf_field() ?>
    <input type="hidden" name="tab" id="activeTab" value="geral">

    <!-- ==================== ABA: GERAL ==================== -->
    <div class="tab-panel active" id="tab-geral" role="tabpanel">

        <!-- Card: Identidade -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Identidade
            </h2>

            <div class="form-group">
                <label for="siteName">Nome do site *</label>
                <input type="text" id="siteName" name="siteName" class="form-input" value="<?= e($siteName) ?>" required>
                <div class="error-inline"></div>
            </div>

            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" class="form-input" rows="3" placeholder="Descreva brevemente o sistema..."><?= e($description) ?></textarea>
                <div class="error-inline"></div>
            </div>
        </div>

        <!-- Card: Preferências -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                Preferências
            </h2>

            <div class="toggle-group">
                <div>
                    <div class="toggle-label-text">Sons ativados</div>
                    <div class="toggle-label-desc">Notificações e efeitos sonoros</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="soundEnabled" value="1" <?= $soundEnabled === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="toggle-group">
                <div>
                    <div class="toggle-label-text">Animações ativadas</div>
                    <div class="toggle-label-desc">Transições e efeitos visuais</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="animationEnabled" value="1" <?= $animationEnabled === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label for="itemsPerPage">Itens por página (5 a 100)</label>
                <input type="number" id="itemsPerPage" name="itemsPerPage" class="form-input" min="5" max="100" value="<?= e($itemsPerPage) ?>" required>
                <div class="error-inline"></div>
            </div>
        </div>

        <!-- Card: Contato -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Contato
            </h2>

            <div class="form-group">
                <label for="supportEmail">E-mail de suporte</label>
                <input type="email" id="supportEmail" name="supportEmail" class="form-input" value="<?= e($supportEmail) ?>" placeholder="suporte@exemplo.com">
                <div class="error-inline"></div>
            </div>
        </div>
    </div><!-- /tab-geral -->

    <!-- ==================== ABA: API & DESENVOLVIMENTO ==================== -->
    <div class="tab-panel" id="tab-api" role="tabpanel">

        <!-- Card: API do aplicativo -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
                API do aplicativo
            </h2>

            <div class="form-group">
                <label for="apiBaseUrl">URL base da API</label>
                <input type="text" id="apiBaseUrl" name="apiBaseUrl" class="form-input" value="<?= e($apiBaseUrl) ?>" placeholder="Ex.: http://192.168.1.100:8080/api">
                <p class="form-help-text">Deixe vazio para usar a URL padrão do sistema.</p>
                <?php if (!empty($errors['apiBaseUrl'])): ?>
                    <div class="error-inline" style="display:block;"><?= e((string) $errors['apiBaseUrl']) ?></div>
                <?php else: ?>
                    <div class="error-inline"></div>
                <?php endif; ?>
            </div>

            <div class="toggle-group">
                <div>
                    <div class="toggle-label-text">Modo desenvolvimento</div>
                    <div class="toggle-label-desc">Habilita painel de testes em rede</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="apiDevMode" value="1" <?= ($apiDevMode === '1') ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <?php if ($devMode): ?>
        <!-- Painel: Ambiente (só aparece quando devMode está ativo no servidor) -->
        <div class="dev-panel">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                Ambiente
            </h2>

            <!-- Indicador do ambiente atual -->
            <div class="env-current-indicator">
                <?php
                $envLabels = [
                    'servidor' => ['icon' => '🌐', 'label' => 'Servidor',  'badge' => 'badge-success'],
                    'rede'     => ['icon' => '📡', 'label' => 'Rede local', 'badge' => 'badge-warning'],
                    'local'    => ['icon' => '💻', 'label' => 'Local',     'badge' => 'badge-info'],
                ];
                $envInfo = $envLabels[$currentEnvironment] ?? $envLabels['servidor'];
                ?>
                <span class="env-current-label">Ambiente ativo:</span>
                <span class="badge <?= e($envInfo['badge']) ?>"><?= $envInfo['icon'] ?> <?= e($envInfo['label']) ?></span>
            </div>

            <!-- Lista de ambientes -->
            <?php if (!empty($environments)): ?>
                <div class="env-list">
                    <?php foreach ($environments as $e): ?>
                        <?php
                        $isCurrent  = !empty($e['is_current']);
                        $envName    = (string) ($e['name'] ?? '');
                        $envDesc    = (string) ($e['desc'] ?? '');
                        $envUrl     = (string) ($e['url']  ?? '');
                        $envBadge   = $envLabels[$e['id'] ?? ''] ?? null;
                        ?>
                        <div class="env-row <?= $isCurrent ? 'env-row--current' : '' ?>">
                            <div class="env-row-header">
                                <span class="env-name">
                                    <?php if ($envBadge): ?>
                                        <?= $envBadge['icon'] ?>
                                    <?php endif; ?>
                                    <?= e($envName) ?>
                                </span>
                                <?php if ($isCurrent): ?>
                                    <span class="badge badge-success" style="font-size:0.65rem;">você está aqui</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($envDesc): ?>
                                <div class="env-desc"><?= e($envDesc) ?></div>
                            <?php endif; ?>
                            <?php if ($envUrl): ?>
                                <div class="env-url-row">
                                    <span class="api-url-box"><span class="url-text"><?= e($envUrl) ?></span></span>
                                    <button type="button" class="btn btn-primary btn-auto btn-sm btn-copy" data-copy="<?= e($envUrl) ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        Copiar
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Fallback: sem lista de ambientes — exibe as linhas clássicas -->
                <div class="dev-info-line">
                    <span class="dev-info-label">IP na rede:</span>
                    <?php if (!empty($lanIp)): ?>
                        <span class="dev-info-value mono"><?= e($lanIp) ?></span>
                    <?php else: ?>
                        <span class="dev-info-value" style="opacity:0.5;">não detectado</span>
                    <?php endif; ?>
                </div>

                <div class="dev-info-line">
                    <span class="dev-info-label">Sistema em rede:</span>
                    <?php if (!empty($systemUrl)): ?>
                        <span class="dev-info-value mono"><?= e($systemUrl) ?></span>
                        <a href="<?= e($systemUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-auto btn-sm">Abrir sistema</a>
                    <?php else: ?>
                        <span class="dev-info-value" style="opacity:0.5;">indisponível</span>
                    <?php endif; ?>
                </div>

                <div class="dev-info-line">
                    <span class="dev-info-label">API para o app:</span>
                    <?php if (!empty($apiUrl)): ?>
                        <span class="api-url-box"><span class="url-text"><?= e($apiUrl) ?></span></span>
                        <button type="button" class="btn btn-primary btn-auto btn-sm btn-copy" data-copy="<?= e($apiUrl) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            Copiar
                        </button>
                    <?php else: ?>
                        <span class="dev-info-value" style="opacity:0.5;">indisponível</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($hasApk): ?>
            <div class="dev-info-line">
                <span class="dev-info-label">Aplicativo Android:</span>
                <a href="/uploads/apk/cacaaotesouro.apk" class="btn btn-secondary btn-auto btn-sm" style="background:rgba(247,236,212,0.1);color:#f7ecd4;border:1px solid rgba(247,236,212,0.2);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Baixar APK
                </a>
            </div>
            <?php endif; ?>

            <div class="dev-help-text">
                No emulador Android use <code style="color:#F97316;">http://10.0.2.2:8080/api</code>.
                Em celular físico use <code style="color:#F97316;">http://IP_DA_MAQUINA:8080/api</code> (mesma rede Wi-Fi).
                Abra a porta 8080 no firewall do Windows para testes.
                <?php if ($currentEnvironment === 'local'): ?>
                    <br><strong style="color:#6fb8e0;">Ambiente local:</strong> o app se conecta em <code style="color:#F97316;">localhost:8080</code>.
                <?php elseif ($currentEnvironment === 'rede'): ?>
                    <br><strong style="color:#F97316;">Ambiente rede:</strong> o app se conecta via <code style="color:#F97316;"><?= e($lanIp ?: 'IP_DA_MAQUINA') ?>:8080</code>.
                <?php else: ?>
                    <br><strong style="color:#5fd99f;">Ambiente servidor:</strong> o app se conecta à URL de produção.
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- DevMode desativado -->
        <div class="settings-card">
            <p style="color:rgba(247,236,212,0.45);font-size:0.92rem;text-align:center;padding:16px 0;">
                Modo desenvolvimento desativado — em produção o app usa a URL configurada acima.
            </p>
        </div>
        <?php endif; ?>

    </div><!-- /tab-api -->

    <!-- ==================== ABA: USUÁRIOS DAS EQUIPES ==================== -->
    <div class="tab-panel" id="tab-equipes" role="tabpanel">

        <p class="form-help-text" style="margin-bottom:24px;">
            Credenciais de acesso das equipes no aplicativo.
        </p>

        <!-- Card: Equipe Laranja -->
        <div class="settings-card team-card team-laranja">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                Equipe Laranja
            </h2>

            <?php foreach (($errors ?? []) as $err): ?>
                <?php if (is_string($err) && str_contains($err, 'Laranja')): ?>
                    <div class="error"><?= e($err) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="teamOrangeUsername">Usuário da Equipe Laranja</label>
                <input type="text" id="teamOrangeUsername" name="teamOrangeUsername" class="form-input" value="<?= e($teamOrangeUsername) ?>" autocomplete="off">
                <div class="error-inline"></div>
            </div>

            <div class="form-group">
                <label for="teamOrangePassword">Senha da Equipe Laranja</label>
                <div class="password-field">
                    <input type="password" id="teamOrangePassword" name="teamOrangePassword" class="form-input" placeholder="Senha atual da equipe (edite se quiser trocar)" autocomplete="new-password" value="<?= e($teamOrangePassword) ?>">
                    <button type="button" class="password-toggle" data-toggle-password="teamOrangePassword" aria-label="Mostrar/ocultar senha">
                        <svg class="icon-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <div class="error-inline"></div>
            </div>
        </div>

        <!-- Card: Equipe Preta -->
        <div class="settings-card team-card team-preta">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                Equipe Preta
            </h2>

            <?php foreach (($errors ?? []) as $err): ?>
                <?php if (is_string($err) && str_contains($err, 'Preta')): ?>
                    <div class="error"><?= e($err) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="teamBlackUsername">Usuário da Equipe Preta</label>
                <input type="text" id="teamBlackUsername" name="teamBlackUsername" class="form-input" value="<?= e($teamBlackUsername) ?>" autocomplete="off">
                <div class="error-inline"></div>
            </div>

            <div class="form-group">
                <label for="teamBlackPassword">Senha da Equipe Preta</label>
                <div class="password-field">
                    <input type="password" id="teamBlackPassword" name="teamBlackPassword" class="form-input" placeholder="Senha atual da equipe (edite se quiser trocar)" autocomplete="new-password" value="<?= e($teamBlackPassword) ?>">
                    <button type="button" class="password-toggle" data-toggle-password="teamBlackPassword" aria-label="Mostrar/ocultar senha">
                        <svg class="icon-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <div class="error-inline"></div>
            </div>
        </div>

        <p class="form-help-text">
            As senhas atuais já estão preenchidas. Para trocar, basta editar o campo; se deixar vazio, a senha é mantida.
        </p>

    </div><!-- /tab-equipes -->

    <!-- ==================== ABA: JOGO ==================== -->
    <div class="tab-panel" id="tab-jogo" role="tabpanel">

        <p class="form-help-text" style="margin-bottom:24px;">
            Regras da partida e credenciais do administrador no aplicativo de gerenciamento.
        </p>

        <!-- Card: Regras da partida -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                Regras da partida
            </h2>

            <?php foreach (($errors ?? []) as $err): ?>
                <?php if (is_string($err) && (str_contains($err, 'ordem dos tesouros') || str_contains($err, 'admin'))): ?>
                    <div class="error"><?= e($err) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="treasureOrder">Ordem dos tesouros</label>
                <select id="treasureOrder" name="treasureOrder" class="form-input">
                    <option value="estabelecida" <?= ($treasureOrder ?? 'estabelecida') === 'estabelecida' ? 'selected' : '' ?>>Estabelecida (ordem de cadastro)</option>
                    <option value="aleatorio" <?= ($treasureOrder ?? '') === 'aleatorio' ? 'selected' : '' ?>>Aleatória (permutação fixa por equipe)</option>
                </select>
                <div class="error-inline"></div>
                <p class="form-help-text">
                    'Estabelecida' usa o sort_order definido na tela de Tesouros. 'Aleatória' sorteia uma ordem fixa para cada equipe no primeiro acesso.
                </p>
            </div>
        </div>

        <!-- Card: Admin da API -->
        <div class="settings-card">
            <h2 class="settings-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Admin do aplicativo (API)
            </h2>

            <div class="form-group">
                <label for="adminUsername">Usuário do admin</label>
                <input type="text" id="adminUsername" name="adminUsername" class="form-input" maxlength="50"
                       value="<?= e($adminUsername ?? 'admin') ?>" autocomplete="off">
                <div class="error-inline"></div>
                <p class="form-help-text">Usado no login do app de gerenciamento (POST /api/admin/login).</p>
            </div>

            <div class="form-group">
                <label for="adminPassword">Senha do admin</label>
                <div class="password-field">
                    <input type="password" id="adminPassword" name="adminPassword" class="form-input"
                           placeholder="Deixe em branco para manter a atual" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-toggle-password="adminPassword" aria-label="Mostrar/ocultar senha">
                        <svg class="icon-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <div class="error-inline"></div>
                <p class="form-help-text">Mínimo 6 caracteres quando preenchida. Em branco mantém a senha atual.</p>
            </div>
        </div>

    </div><!-- /tab-jogo -->

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
            </svg>
            Salvar alterações
        </button>
        <a href="/tesouros" class="btn btn-ghost btn-auto" style="margin-left:12px;">Cancelar</a>
    </div>
</form>

<!-- ═══ Aba LIMPEZA (fora do form principal — form próprio) ═══ -->
<div class="tab-panel" id="tab-limpeza" role="tabpanel">
    <div class="page-header">
        <h1 class="page-title">Limpeza</h1>
        <p class="page-subtitle">Apaga todo o progresso e deixa o sistema pronto para uma nova caçada.</p>
    </div>

    <div class="settings-card" style="border:1px solid rgba(192,57,43,0.4); background:rgba(192,57,43,0.06);">
        <h2 class="settings-card-title" style="color:#ef5350;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Resetar o jogo
        </h2>

        <p style="color:rgba(247,236,212,0.7); font-size:0.9rem; line-height:1.7; margin-bottom:16px;">
            Esta ação <strong style="color:#ef5350;">apaga tudo</strong> e deixa o sistema vazio, pronto para começar:
        </p>
        <ul style="color:rgba(247,236,212,0.65); font-size:0.88rem; line-height:1.8; margin:0 0 20px; padding-left:18px;">
            <li>Progresso dos tesouros (charadas respondidas, selfies e GPS confirmado)</li>
            <li><strong>Todas as selfies</strong> enviadas (arquivos e registros)</li>
            <li>Tesouros cadastrados e seus QR codes</li>
            <li>Log de pontos, localizações e mensagens das equipes</li>
            <li>Equipes voltam a 100 pontos, sem sessão e sem progresso</li>
            <li>Jogo volta ao estado inicial (em andamento, janela 08:00–17:00)</li>
        </ul>

        <form method="post" action="/limpar" onsubmit="return confirm('⚠️ ATENÇÃO: isso apagará TODO o progresso, todas as selfies e todos os tesouros cadastrados. O sistema ficará vazio. Tem certeza?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" style="width:auto;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Apagar tudo e recomeçar
            </button>
        </form>
    </div>

    <div class="settings-card" style="border:1px solid rgba(245,197,66,0.25); margin-top:24px;">
        <h2 class="settings-card-title" style="color:#F97316;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Resetar somente o jogo (manter configurações)
        </h2>

        <p style="color:rgba(247,236,212,0.7); font-size:0.9rem; line-height:1.7; margin-bottom:16px;">
            Reseta o jogo das duas equipes, mas <strong style="color:#F97316;">mantém as configurações</strong>
            (data/horários, desafio final, credenciais e ordem dos tesouros):
        </p>
        <ul style="color:rgba(247,236,212,0.65); font-size:0.88rem; line-height:1.8; margin:0 0 20px; padding-left:18px;">
            <li>Equipes voltam a <strong>100 pontos</strong>, sem sessão e sem progresso</li>
            <li><strong>Todas as selfies</strong> apagadas (registros e arquivos)</li>
            <li><strong>Tesouros</strong> apagados (registros e QR codes)</li>
            <li>Log de pontos, localizações e mensagens apagados</li>
            <li>Jogo volta a "em andamento"</li>
        </ul>

        <form method="post" action="/limpar-jogo" onsubmit="return confirm('⚠️ Isso apagará o progresso das equipes, as selfies e os tesouros. As configurações serão mantidas. Continuar?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" style="width:auto; background:linear-gradient(135deg,#F97316,#EA580C);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Resetar somente o jogo
            </button>
        </form>
    </div>

    <div class="settings-card" style="border:1px solid rgba(39,174,96,0.3); margin-top:24px;">
        <h2 class="settings-card-title" style="color:#27ae60;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="16"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
            Tesouros de demonstração
        </h2>

        <p style="color:rgba(247,236,212,0.7); font-size:0.9rem; line-height:1.7; margin-bottom:16px;">
            Cria <strong style="color:#27ae60;">5 tesouros</strong> de demonstração (T01–T05) com charadas, QR codes e
            ordenação automática. Útil logo após a limpeza. Depois, confirme as coordenadas de cada um pelo app admin.
        </p>

        <form method="post" action="/limpar/tesouros">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="width:auto; background:linear-gradient(135deg,#27ae60,#1e8a4c);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 12v10H4V12"/>
                    <path d="M12 2v15"/>
                    <path d="M8 6l4-4 4 4"/>
                </svg>
                Criar 5 tesouros de demonstração
            </button>
        </form>
    </div>
</div>
