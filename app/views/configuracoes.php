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
    <button type="button" class="tab-btn" data-tab="backup" role="tab" aria-selected="false" aria-controls="tab-backup">
        💾 Backup
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
            <div style="margin-top:18px; padding:18px; border:1px solid rgba(34,197,94,0.3); border-radius:14px; background:rgba(34,197,94,0.05);">
                <div style="font-weight:700; color:#22C55E; margin-bottom:8px; font-size:.85rem; text-transform:uppercase; letter-spacing:.06em;">📱 Instalar o aplicativo</div>
                <p style="font-size:.85rem; color:rgba(247,236,212,.75); margin-bottom:14px;">
                    Link para baixar o APK (servidor de produção):
                    <a href="<?= e($apkUrl) ?>" target="_blank" rel="noopener" style="color:#22C55E; font-weight:600; word-break:break-all;"><?= e($apkUrl) ?></a>
                </p>
                <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <div style="background:#fff; padding:10px; border-radius:10px; box-shadow:0 4px 14px rgba(0,0,0,.3); flex-shrink:0;">
                        <?= $apkQrSvg ?>
                    </div>
                    <div style="flex:1; min-width:200px; font-size:.82rem; color:rgba(247,236,212,.7); line-height:1.7;">
                        Aponte a câmera de outro celular para o <strong style="color:#22C55E;">QR code</strong> ao lado
                        para baixar e instalar o aplicativo.
                        <br><br>
                        <a href="<?= e($apkUrl) ?>" class="btn btn-primary btn-auto btn-sm" style="background:linear-gradient(135deg,#22C55E,#168a3a);">
                            Baixar APK
                        </a>
                        &nbsp;
                        <a href="/admin/apk-qr.svg" class="btn btn-secondary btn-auto btn-sm" style="background:rgba(247,236,212,0.1);color:#f7ecd4;border:1px solid rgba(247,236,212,0.2);">
                            Baixar QR SVG
                        </a>
                    </div>
                </div>
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
        <p class="page-subtitle">Escolha o que deseja limpar. As ações mantêm o que você indica.</p>
    </div>

    <!-- ── 1. LIMPEZA LEVE (recomendada) ──────────────────── -->
    <div class="settings-card" style="border:1px solid rgba(34,197,94,0.4); background:rgba(34,197,94,0.05);">
        <h2 class="settings-card-title" style="color:#22C55E;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Limpar o jogo (recomendado)
        </h2>
        <p style="color:rgba(247,236,212,0.7); font-size:0.9rem; line-height:1.7; margin-bottom:16px;">
            Apaga os <strong style="color:#22C55E;">tesouros completados</strong> e as selfies, e reseta as equipes.
            <strong>Mantém</strong> os tesouros cadastrados, a história e o desafio final.
            Após limpar, os tesouros <strong style="color:#22C55E;">precisarão ter as coordenadas confirmadas novamente</strong> pelo app admin.
        </p>
        <ul style="color:rgba(247,236,212,0.65); font-size:0.88rem; line-height:1.8; margin:0 0 20px; padding-left:18px;">
            <li>✅ Tesouros cadastrados e QR codes <strong>mantidos</strong></li>
            <li>✅ História e desafio final <strong>mantidos</strong></li>
            <li>🗑️ Tesouros completados e selfies apagados</li>
            <li>🗑️ Pontos, localizações e mensagens apagados</li>
            <li>↩️ Equipes voltam a 100 pontos, sem progresso</li>
            <li>📍 Tesouros ficam <strong>inativos</strong> — confirme as coordenadas no local pelo app admin</li>
        </ul>
        <form method="post" action="/limpar/leve" onsubmit="return confirm('Limpar o jogo? Os tesouros completados e selfies serão apagados. Tesouros, história e desafio final serão mantidos.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="width:auto; background:linear-gradient(135deg,#22C55E,#168a3a);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Limpar o jogo
            </button>
        </form>
    </div>

    <!-- ── 2. FERRAMENTAS ─────────────────────────────────── -->
    <div style="margin:28px 0 10px; font-size:0.72rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:rgba(247,236,212,0.4);">
        Ferramentas
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div class="settings-card" style="margin:0;">
            <h2 class="settings-card-title" style="color:#F97316; font-size:1rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                Reexibir história
            </h2>
            <p style="color:rgba(247,236,212,0.6); font-size:0.82rem; line-height:1.6; margin-bottom:14px;">
                A história aparece novamente no próximo acesso das equipes.
            </p>
            <form method="post" action="/limpar/historia">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#F97316,#EA580C);">
                    Reexibir às equipes
                </button>
            </form>
        </div>

        <div class="settings-card" style="margin:0;">
            <h2 class="settings-card-title" style="color:#27ae60; font-size:1rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                Criar 5 tesouros
            </h2>
            <p style="color:rgba(247,236,212,0.6); font-size:0.82rem; line-height:1.6; margin-bottom:14px;">
                Cria 5 tesouros de demonstração (T01–T05) com charadas e QR.
            </p>
            <form method="post" action="/limpar/tesouros">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#27ae60,#1e8a4c);">
                    Criar tesouros
                </button>
            </form>
        </div>
    </div>

    <!-- ── 3. ZONA DE PERIGO ──────────────────────────────── -->
    <div style="margin:28px 0 10px; font-size:0.72rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#ef5350;">
        Zona de perigo
    </div>

    <div class="settings-card" style="border:1px solid rgba(239,68,68,0.35); background:rgba(239,68,68,0.05);">
        <h2 class="settings-card-title" style="color:#ef5350; font-size:1.05rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Resetar somente o jogo
        </h2>
        <p style="color:rgba(247,236,212,0.7); font-size:0.85rem; line-height:1.6; margin-bottom:14px;">
            Como a limpeza leve, mas <strong style="color:#ef5350;">também apaga os tesouros cadastrados</strong>
            (mantém data/horários, desafio final e credenciais).
        </p>
        <form method="post" action="/limpar-jogo" onsubmit="return confirm('⚠️ Isso apagará o progresso, as selfies E os tesouros cadastrados. Continuar?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm" style="width:auto;">
                Resetar o jogo (apaga tesouros)
            </button>
        </form>
    </div>

    <div class="settings-card" style="border:1px solid rgba(239,68,68,0.5); background:rgba(239,68,68,0.08); margin-top:16px;">
        <h2 class="settings-card-title" style="color:#ef5350; font-size:1.05rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Apagar tudo e recomeçar
        </h2>
        <p style="color:rgba(247,236,212,0.7); font-size:0.85rem; line-height:1.6; margin-bottom:14px;">
            Apaga <strong style="color:#ef5350;">tudo</strong> e restaura as configurações padrão — o sistema fica vazio.
        </p>
        <form method="post" action="/limpar" onsubmit="return confirm('⚠️ ATENÇÃO: apagará TODO o progresso, selfies, tesouros e restaurará as configurações padrão. Tem certeza?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm" style="width:auto;">
                Apagar tudo e recomeçar
            </button>
        </form>
    </div>
</div>

<!-- ═══ Aba BACKUP (tesouros em JSON) ═══ -->
<div class="tab-panel" id="tab-backup" role="tabpanel">
    <div class="page-header">
        <h1 class="page-title">Backup de Tesouros</h1>
        <p class="page-subtitle">Exporte os tesouros em JSON ou importe de um backup para adicioná-los automaticamente.</p>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <!-- Exportar -->
        <div class="settings-card" style="margin:0;">
            <h2 class="settings-card-title" style="color:#27ae60;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Baixar tesouros (JSON)
            </h2>
            <p style="color:rgba(247,236,212,0.6); font-size:0.85rem; line-height:1.6; margin-bottom:14px;">
                Gera um arquivo <strong style="color:#27ae60;">.json</strong> com todos os tesouros cadastrados
                (código, nome, descrição, dica, charadas e respostas). Use para guardar ou transferir para outro sistema.
            </p>
            <a href="/admin/backup" class="btn btn-primary btn-auto btn-sm" style="background:linear-gradient(135deg,#27ae60,#1e8a4c);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Baixar backup JSON
            </a>
        </div>

        <!-- Importar -->
        <div class="settings-card" style="margin:0;">
            <h2 class="settings-card-title" style="color:#F97316;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Importar tesouros (JSON)
            </h2>
            <p style="color:rgba(247,236,212,0.6); font-size:0.85rem; line-height:1.6; margin-bottom:14px;">
                Envie um arquivo <strong style="color:#F97316;">.json</strong> de backup. Os tesouros são
                <strong>adicionados automaticamente</strong> (QR SVG gerado). Códigos já existentes são ignorados.
            </p>
            <form method="post" action="/admin/import" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="file" name="file" accept=".json,application/json" required
                           style="flex:1; min-width:160px; font-size:.8rem; color:#f7ecd4;
                                  background:rgba(5,11,18,.6); border:1px solid rgba(249,115,22,.25);
                                  border-radius:8px; padding:8px 10px;">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#F97316,#EA580C);">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="settings-card" style="margin-top:20px; border:1px solid rgba(247,236,212,0.12);">
        <h2 class="settings-card-title" style="color:rgba(247,236,212,0.8); font-size:1rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            Formato do JSON
        </h2>
        <pre style="background:rgba(5,11,18,.6); border:1px solid rgba(247,236,212,0.12); border-radius:8px; padding:14px; font-size:.78rem; color:rgba(247,236,212,.75); overflow-x:auto; margin:0;">
{
  "treasures": [
    {
      "code": "T01",
      "name": "Praça do Quico",
      "description": "A praça onde o Quico brinca.",
      "clue": "Procure o banco onde o Quico senta.",
      "riddle1": "Quantas pernas tem o total de personagens da vila?",
      "answer1": "0412",
      "riddle2": "Qual o número da casa da bruxa do 71?",
      "answer2": "0071"
    }
  ]
}</pre>
    </div>
</div>
