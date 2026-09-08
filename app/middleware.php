<?php

declare(strict_types=1);

/**
 * Middleware reutilizáveis do Slim 4.
 *
 * O arquivo retorna um array associativo de callables:
 *
 *   'startSession'  -> inicia a sessão PHP (deve rodar por primeiro)
 *   'csrf'          -> exige token CSRF em métodos que alteram estado
 *   'authRequired'  -> exige usuário autenticado (redireciona para /login)
 *   'guestOnly'     -> bloqueia usuários autenticados (redireciona para /)
 *
 * Para que o middleware execute na ordem correta, adicione no bootstrap
 * na ordem inversa (o último adicionado é o primeiro a executar).
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

return [
    /**
     * Inicia a sessão PHP com cookies HttpOnly + SameSite=Lax.
     * Adicionar por último no App para executar por primeiro.
     */
    'startSession' => function (Request $request, RequestHandler $handler): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = (string) app_config('session.name', 'cacaaotesouro_session');
            $lifetime = (int) app_config('session.lifetime', 604800);

            session_name($name);

            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        return $handler->handle($request);
    },

    /**
     * Exige token CSRF válido em requisições POST/PUT/DELETE/PATCH.
     *
     * Rotas cujo path começa com /api são EXCETUADAS: o aplicativo móvel
     * não envia token CSRF e a autenticação é feita manualmente dentro do
     * ApiController (lê a sessão iniciada pelo middleware startSession).
     */
    'csrf' => function (Request $request, RequestHandler $handler): Response {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, '/api')) {
            return $handler->handle($request);
        }

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) && !csrf_verify()) {
            $response = new SlimResponse(400);
            $response->getBody()->write('Requisição inválida.');

            return $response;
        }

        return $handler->handle($request);
    },

    /**
     * Exige usuário autenticado; caso contrário redireciona para /login.
     */
    'authRequired' => function (Request $request, RequestHandler $handler): Response {
        if (!isset($_SESSION['user'])) {
            flash_set('error', 'Faça login para continuar.');
            redirect('/login');
        }

        return $handler->handle($request);
    },

    /**
     * Bloqueia usuários autenticados; redireciona para /.
     */
    'guestOnly' => function (Request $request, RequestHandler $handler): Response {
        if (isset($_SESSION['user'])) {
            redirect('/');
        }

        return $handler->handle($request);
    },
];