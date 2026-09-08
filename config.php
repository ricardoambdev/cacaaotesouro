<?php

declare(strict_types=1);

/**
 * Configuração global do sistema Caça ao Tesouro.
 *
 * Edite este arquivo para apontar para o banco do seu servidor compartilhado.
 * Em produção, prefira definir as variáveis de ambiente (getenv) no painel
 * de hospedagem para não expor credenciais no código.
 */

return [
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