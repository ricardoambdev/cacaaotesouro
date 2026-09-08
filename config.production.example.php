<?php

declare(strict_types=1);

/**
 * Template de configuração para PRODUÇÃO (servidor compartilhado).
 *
 * COMO USAR:
 *   1. Copie este arquivo para `config.php` no servidor
 *      (ou edite o config.php enviado no pacote de deploy).
 *   2. Preencha as credenciais do banco criado no cPanel.
 *   3. O recomendado é usar VARIÁVEIS DE AMBIENTE (getenv) definidas
 *      no MultiPHP INI Editor / .htaccess — assim as credenciais não
 *      ficam no código. Se preferir, edite os valores abaixo.
 *
 * IMPORTANTE: nunca deixe APP_ENV=dev em produção.
 */

return [
    'app' => [
        // 'prod' -> envia e-mail real via mail() (recuperação de senha),
        //          esconde links de desenvolvimento
        'env'      => getenv('APP_ENV') ?: 'prod',
        // URL pública real (ex.: https://caaotesouro.dominio.com.br)
        'url'      => getenv('APP_URL') ?: 'https://SEU-DOMINIO.com',
        'name'     => 'Caça ao Tesouro',
        'timezone' => 'America/Sao_Paulo',
    ],

    'db' => [
        // SEMPRE 'mysql' em servidor compartilhado
        'driver'      => getenv('DB_DRIVER') ?: 'mysql',
        'host'        => getenv('DB_HOST') ?: 'localhost',
        'port'        => getenv('DB_PORT') ?: '3306',
        // Nome do banco criado no cPanel (ex.: usuario_cacaaotesouro)
        'name'        => getenv('DB_NAME') ?: 'SEU_BANCO',
        'user'        => getenv('DB_USER') ?: 'SEU_USUARIO',
        'pass'        => getenv('DB_PASS') ?: 'SUA_SENHA',
        // Usado somente quando driver = sqlite (não usar em produção)
        'sqlite_path' => __DIR__ . '/data/cacaaotesouro.sqlite',
    ],

    'session' => [
        'name'     => 'cacaaotesouro_session',
        'lifetime' => 60 * 60 * 24 * 7, // 7 dias
    ],
];