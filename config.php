<?php

declare(strict_types=1);

/**
 * Configuração global do sistema Caça ao Tesouro.
 *
 * A configuração é carregada nesta ordem (a última vence):
 *   1. Valores padrão abaixo.
 *   2. Variáveis de ambiente (getenv) — recomendado em produção.
 *   3. Arquivo data/install.php — gravado pelo instalador no primeiro
 *      acesso (quando o banco ainda não está configurado).
 */

$config = [
    'app' => [
        // 'dev'  -> mostra links de recuperação de senha na tela (sem SMTP real)
        // 'prod' -> envia e-mail real via mail()
        'env'      => getenv('APP_ENV') ?: 'dev',
        // URL pública do sistema (usada para montar links de recuperação)
        'url'      => getenv('APP_URL') ?: 'http://localhost:8080',
        'name'     => 'Caça ao Tesouro',
        'timezone' => 'America/Sao_Paulo',
    ],

    'db' => [
        // 'mysql'  (recomendado — usado no servidor compartilhado e no MySQL local)
        // 'sqlite' (alternativa para desenvolvimento sem servidor MySQL)
        'driver'      => getenv('DB_DRIVER') ?: 'mysql',
        'host'        => getenv('DB_HOST') ?: 'localhost',
        'port'        => getenv('DB_PORT') ?: '3306',
        'name'        => getenv('DB_NAME') ?: 'cacaaotesouro',
        'user'        => getenv('DB_USER') ?: 'root',
        'pass'        => getenv('DB_PASS') ?: '',
        // Usado somente quando driver = sqlite
        'sqlite_path' => __DIR__ . '/data/cacaaotesouro.sqlite',
    ],

    'session' => [
        'name'     => 'cacaaotesouro_session',
        'lifetime' => 60 * 60 * 24 * 7, // 7 dias
    ],
];

// Configuração gravada pelo instalador (data/install.php) — se existir,
// tem prioridade sobre os valores padrão acima.
$installFile = __DIR__ . '/data/install.php';

if (is_file($installFile)) {
    $local = require $installFile;

    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;