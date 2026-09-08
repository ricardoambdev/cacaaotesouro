<?php

declare(strict_types=1);

/**
 * Front controller do sistema Caça ao Tesouro.
 *
 * Em servidores compartilhados (Apache/cPanel), o document root deve
 * apontar para esta pasta (public/). Todas as requisições que não são
 * arquivos estáticos são redirecionadas para cá pelo public/.htaccess.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$app->run();