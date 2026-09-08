<?php

declare(strict_types=1);

/**
 * Front controller do sistema Caça ao Tesouro.
 *
 * Em servidores compartilhados (Apache/cPanel), o document root deve
 * apontar para esta pasta (public/) — ou o .htaccess da raiz resolve.
 * Todas as requisições que não são arquivos estáticos são redirecionadas
 * para cá pelo public/.htaccess.
 *
 * FLUXO DE INSTALAÇÃO: se o banco ainda não foi configurado
 * (data/install.php ausente) ou a conexão falhar, o instalador
 * (setup.php) é exibido no primeiro acesso.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/helpers.php';

// ── Verificação de instalação (banco de dados) ──────────────────
$installFile = dirname(__DIR__) . '/data/install.php';
$needsSetup = !is_file($installFile);

if (!$needsSetup) {
    try {
        \App\Database::get();
    } catch (\Throwable $e) {
        $needsSetup = true;
    }
}

if ($needsSetup) {
    require_once dirname(__DIR__) . '/public/setup.php';
    exit;
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$app->run();