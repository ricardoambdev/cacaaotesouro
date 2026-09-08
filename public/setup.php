<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  Caça ao Tesouro — INSTALADOR (primeiro acesso)
 *
 *  Exibido automaticamente quando o banco de dados ainda não está
 *  configurado (data/install.php ausente) ou quando a conexão falha.
 *  Ao configurar e conectar, cria as tabelas e semeia os dados
 *  primários (admin, equipes, settings e tesouros iniciais).
 * ═══════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/helpers.php';

use App\Database;

$projectRoot = dirname(__DIR__);
$installFile = $projectRoot . '/data/install.php';
$dataDir = $projectRoot . '/data';

// Se já está instalado E o banco conecta, sai do instalador.
if (is_file($installFile)) {
    try {
        Database::get();
        redirect('/login');
    } catch (\Throwable $e) {
        // Conexão falhou → segue para o instalador (reconfigurar).
    }
}

// Valores atuais (para pré-preencher o formulário).
$current = [];
if (is_file($installFile)) {
    $loaded = require $installFile;
    if (is_array($loaded)) {
        $current = $loaded;
    }
}

$driver = $current['db']['driver'] ?? app_config('db.driver', 'mysql');
$host   = $current['db']['host'] ?? app_config('db.host', 'localhost');
$port   = $current['db']['port'] ?? app_config('db.port', '3306');
$name   = $current['db']['name'] ?? app_config('db.name', 'cacaaotesouro');
$user   = $current['db']['user'] ?? app_config('db.user', 'root');
$pass   = $current['db']['pass'] ?? app_config('db.pass', '');
$siteName = $current['siteName'] ?? app_config('app.name', 'Caça ao Tesouro');
$appUrl   = $current['app']['url'] ?? app_config('app.url', '');

$errors = [];
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $driver   = strtolower(trim((string) ($_POST['db_driver'] ?? 'mysql')));
    $host     = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $port     = trim((string) ($_POST['db_port'] ?? '3306'));
    $name     = trim((string) ($_POST['db_name'] ?? ''));
    $user     = trim((string) ($_POST['db_user'] ?? ''));
    $pass     = (string) ($_POST['db_pass'] ?? '');
    $siteName = trim((string) ($_POST['site_name'] ?? 'Caça ao Tesouro'));
    $appUrl   = trim((string) ($_POST['app_url'] ?? ''));

    if (!in_array($driver, ['mysql', 'sqlite'], true)) {
        $errors[] = 'Driver de banco inválido.';
    }
    if ($siteName === '') {
        $errors[] = 'Informe o nome do site.';
    }
    if ($driver === 'mysql') {
        if ($host === '') {
            $host = 'localhost';
        }
        if ($port === '') {
            $port = '3306';
        }
        if ($name === '') {
            $errors[] = 'Informe o nome do banco de dados.';
        }
        if ($user === '') {
            $errors[] = 'Informe o usuário do banco de dados.';
        }
    }

    if ($errors === []) {
        try {
            if ($driver === 'sqlite') {
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0775, true);
                }
                $pdo = new PDO('sqlite:' . $dataDir . '/cacaaotesouro.sqlite');
            } else {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

                try {
                    $pdo = new PDO($dsn, $user, $pass, $options);
                } catch (PDOException $e) {
                    // Banco inexistente (1049): tenta criar automaticamente.
                    if ((int) ($e->errorInfo[1] ?? 0) === 1049) {
                        $escaped = str_replace('`', '``', $name);
                        $server = new PDO(
                            "mysql:host=$host;port=$port;charset=utf8mb4",
                            $user,
                            $pass,
                            $options
                        );
                        $server->exec(
                            "CREATE DATABASE IF NOT EXISTS `$escaped` "
                            . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                        );
                        $server = null;
                        $pdo = new PDO($dsn, $user, $pass, $options);
                    } else {
                        throw $e;
                    }
                }
            }

            $pdo = null;

            // ── Grava a configuração do banco ──────────────────
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0775, true);
            }

            $installContent = "<?php\n\n// Gerado automaticamente pelo instalador.\nreturn [\n"
                . "    'siteName' => " . var_export($siteName, true) . ",\n"
                . "    'app' => ['url' => " . var_export($appUrl, true) . "],\n"
                . "    'db' => [\n"
                . "        'driver' => " . var_export($driver, true) . ",\n"
                . "        'host'   => " . var_export($host, true) . ",\n"
                . "        'port'   => " . var_export($port, true) . ",\n"
                . "        'name'   => " . var_export($name, true) . ",\n"
                . "        'user'   => " . var_export($user, true) . ",\n"
                . "        'pass'   => " . var_export($pass, true) . ",\n"
                . "    ],\n"
                . "];\n";

            file_put_contents($installFile, $installContent, LOCK_EX);

            // Relê a configuração (agora com o banco recém-gravado).
            app_config_reload();

            // ── Cria tabelas + dados primários ─────────────────
            try {
                Database::boot();
                Database::seedPrimaryData();

                if ($siteName !== '') {
                    try {
                        \App\Repositories\SettingsRepository::set('siteName', $siteName);
                    } catch (\Throwable $e) {
                        error_log('setup siteName: ' . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                // Falha na instalação → remove a configuração e volta ao instalador.
                @unlink($installFile);
                app_config_reload();
                throw $e;
            }

            $success = true;
        } catch (\Throwable $e) {
            $errors[] = 'Não foi possível conectar: ' . $e->getMessage();
        }
    }

    if ($success) {
        redirect('/login');
    }
}

$flashError = $errors !== [] ? implode(' ', $errors) : '';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação — Caça ao Tesouro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Pirata+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>
        body { margin: 0; background: linear-gradient(160deg, #0d1b2a 0%, #162a3a 100%); min-height: 100vh; font-family: 'Inter', sans-serif; }
        .install-card { background: rgba(22, 42, 58, 0.92); border: 1px solid rgba(245, 197, 66, 0.15); border-radius: 20px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45); }
        .install-title { font-family: 'Pirata One', Georgia, cursive; color: #f5c542; }
        .fld { width: 100%; padding: 12px 16px; background: rgba(13, 27, 42, 0.6); border: 1.5px solid rgba(247, 236, 212, 0.2); border-radius: 10px; color: #f7ecd4; font-size: 0.95rem; outline: none; box-sizing: border-box; }
        .fld:focus { border-color: #f5c542; box-shadow: 0 0 0 3px rgba(245, 197, 66, 0.3); }
        .fld::placeholder { color: rgba(247, 236, 212, 0.45); }
        .lbl { display: block; font-size: 0.82rem; font-weight: 500; color: rgba(247, 236, 212, 0.7); margin-bottom: 7px; letter-spacing: 0.03em; text-transform: uppercase; }
        .btn-primary { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 13px 28px; border: none; border-radius: 10px; background: linear-gradient(135deg, #f5c542, #d4a017); color: #0d1b2a; font-weight: 600; font-size: 0.95rem; cursor: pointer; box-shadow: 0 4px 16px rgba(245, 197, 66, 0.3); }
        .btn-primary:hover { box-shadow: 0 6px 24px rgba(245, 197, 66, 0.45); }
        select.fld { appearance: auto; }
    </style>
</head>
<body>
    <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; box-sizing:border-box;">
        <div class="install-card" style="width:100%; max-width:520px; padding:40px 36px;">
            <h1 class="install-title" style="font-size:2rem; margin:0 0 4px; text-align:center;">Instalação</h1>
            <p style="color:rgba(247,236,212,0.55); font-size:0.9rem; text-align:center; margin:0 0 28px;">
                Configure o banco de dados para começar.
            </p>

            <?php if ($flashError !== ''): ?>
                <div style="background:rgba(192,57,43,0.15); border:1px solid rgba(192,57,43,0.5); color:#f5a6a0; padding:12px 16px; border-radius:10px; font-size:0.88rem; margin-bottom:20px; line-height:1.5;">
                    <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div style="margin-bottom:20px;">
                    <label class="lbl" for="db_driver">Banco de dados</label>
                    <select class="fld" id="db_driver" name="db_driver">
                        <option value="mysql" <?= $driver === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB (recomendado)</option>
                        <option value="sqlite" <?= $driver === 'sqlite' ? 'selected' : '' ?>>SQLite (local / teste)</option>
                    </select>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="lbl" for="site_name">Nome do site</label>
                    <input class="fld" id="site_name" name="site_name" value="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div style="display:flex; gap:12px;">
                    <div style="flex:3; margin-bottom:20px;">
                        <label class="lbl" for="db_host">Host</label>
                        <input class="fld" id="db_host" name="db_host" value="<?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div style="flex:1; margin-bottom:20px;">
                        <label class="lbl" for="db_port">Porta</label>
                        <input class="fld" id="db_port" name="db_port" value="<?= htmlspecialchars($port, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="lbl" for="db_name">Nome do banco</label>
                    <input class="fld" id="db_name" name="db_name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario_cacaaotesouro">
                </div>

                <div style="margin-bottom:20px;">
                    <label class="lbl" for="db_user">Usuário</label>
                    <input class="fld" id="db_user" name="db_user" value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div style="margin-bottom:20px;">
                    <label class="lbl" for="db_pass">Senha</label>
                    <input class="fld" id="db_pass" name="db_pass" type="password" value="<?= htmlspecialchars($pass, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div style="margin-bottom:28px;">
                    <label class="lbl" for="app_url">URL do sistema (opcional)</label>
                    <input class="fld" id="app_url" name="app_url" value="<?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://SEU-DOMINIO.com">
                    <p style="font-size:0.78rem; color:rgba(247,236,212,0.45); margin:6px 0 0;">Usada nos links de recuperação de senha e na API.</p>
                </div>

                <button type="submit" class="btn-primary">Instalar sistema</button>
            </form>

            <p style="font-size:0.78rem; color:rgba(247,236,212,0.4); text-align:center; margin:20px 0 0;">
                Ao instalar, o sistema cria as tabelas e os dados iniciais:<br>
                admin/<b>admin1234</b> · equipe_laranja/<b>laranja123</b> · equipe_preta/<b>preta123</b> · 10 tesouros.
            </p>
        </div>
    </div>
</body>
</html>