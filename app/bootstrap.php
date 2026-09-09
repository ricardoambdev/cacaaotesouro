<?php

declare(strict_types=1);

/**
 * Bootstrap da aplicação Slim 4.
 *
 * Cria, configura e RETORNA a instância da App. O front controller
 * (public/index.php) executa $app->run().
 *
 * Ordem dos middleware (importante no Slim 4): o ÚLTIMO adicionado é o
 * PRIMEIRO a executar. Portanto, adicionamos por último o que deve rodar
 * primeiro (startSession), garantindo que a sessão exista antes do CSRF,
 * do roteamento e de qualquer controlador.
 */

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GameController;
use App\Controllers\SettingsController;
use App\Controllers\TreasureController;
use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/helpers.php';

date_default_timezone_set((string) app_config('app.timezone', 'America/Sao_Paulo'));

$app = AppFactory::create();

$mw = require __DIR__ . '/middleware.php';

// ---------------------------------------------------------------------
// Camadas do App (o último add() abaixo é o primeiro a executar)
// Execução: startSession -> csrf -> error -> bodyParsing -> routing
// ---------------------------------------------------------------------

// Roteamento e parsing de body ficam no núcleo.
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Tratamento de erros 404/500 com páginas HTML simples e seguras.
$errorMiddleware = $app->addErrorMiddleware(false, true, true);

$errorMiddleware->setDefaultErrorHandler(
    function (
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) use ($app): Response {
        $status = 500;
        $title = 'Erro interno do servidor';
        $message = 'Ocorreu um erro inesperado. Tente novamente mais tarde.';

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $title = 'Página não encontrada';
            $message = 'A página que você procura não existe ou foi movida.';
        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $title = 'Método não permitido';
            $message = 'Este endereço não aceita esse tipo de requisição.';
        }

        $body = '<!DOCTYPE html>'
            . '<html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $status . ' - ' . e($title) . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:60px 20px;">'
            . '<h1 style="font-size:56px;margin:0 0 12px;">' . $status . '</h1>'
            . '<p style="font-size:18px;">' . e($message) . '</p>'
            . '<p><a href="/">Voltar ao início</a></p>'
            . '</body></html>';

        $response = $app->getResponseFactory()->createResponse($status);
        $response->getBody()->write($body);

        return $response;
    }
);

// CSRF valida todos os POST/PUT/DELETE/PATCH (roda por segundo).
$app->add($mw['csrf']);

// Sessão roda por primeiro (fica fora de tudo, inclusive do CSRF).
$app->add($mw['startSession']);

// ---------------------------------------------------------------------
// Banco de dados: cria o esquema e semeia as configurações padrão.
// ---------------------------------------------------------------------
Database::boot();

// ---------------------------------------------------------------------
// Rotas WEB (painel admin) — authRequired + CSRF global.
// ---------------------------------------------------------------------

// Painel
$app->get('/', [DashboardController::class, 'index'])->add($mw['authRequired']);

// Autenticação
$app->get('/login', [AuthController::class, 'showLogin'])->add($mw['guestOnly']);
$app->post('/login', [AuthController::class, 'login'])->add($mw['guestOnly']);

$app->get('/register', [AuthController::class, 'showRegister'])->add($mw['guestOnly']);
$app->post('/register', [AuthController::class, 'register'])->add($mw['guestOnly']);

$app->post('/logout', [AuthController::class, 'logout'])->add($mw['authRequired']);

// Recuperação de senha
$app->get('/recuperar', [AuthController::class, 'showRecover'])->add($mw['guestOnly']);
$app->post('/recuperar', [AuthController::class, 'recover'])->add($mw['guestOnly']);

$app->get('/redefinir', [AuthController::class, 'showReset'])->add($mw['guestOnly']);
$app->post('/redefinir', [AuthController::class, 'reset'])->add($mw['guestOnly']);

// Configurações
$app->get('/configuracoes', [SettingsController::class, 'show'])->add($mw['authRequired']);
$app->post('/configuracoes', [SettingsController::class, 'update'])->add($mw['authRequired']);

// Tesouros
$app->get('/tesouros', [TreasureController::class, 'index'])->add($mw['authRequired']);
$app->get('/tesouros/novo', [TreasureController::class, 'createForm'])->add($mw['authRequired']);
$app->post('/tesouros/novo', [TreasureController::class, 'store'])->add($mw['authRequired']);
$app->get('/tesouros/{id}/editar', [TreasureController::class, 'editForm'])->add($mw['authRequired']);
$app->post('/tesouros/{id}/editar', [TreasureController::class, 'update'])->add($mw['authRequired']);
$app->post('/tesouros/{id}/excluir', [TreasureController::class, 'delete'])->add($mw['authRequired']);
$app->post('/tesouros/reorder', [TreasureController::class, 'reorder'])->add($mw['authRequired']);

// Jogo (história, desafio final e estado geral)
$app->get('/historia', [GameController::class, 'history'])->add($mw['authRequired']);
$app->post('/historia', [GameController::class, 'history'])->add($mw['authRequired']);
$app->get('/desafio-final', [GameController::class, 'finalChallenge'])->add($mw['authRequired']);
$app->post('/desafio-final', [GameController::class, 'finalChallenge'])->add($mw['authRequired']);
$app->get('/jogo', [GameController::class, 'status'])->add($mw['authRequired']);

// Telão (PÚBLICO — sem authRequired). Página autônoma que consome
// GET /api/telao; o CSRF global só atinge POST/PUT/DELETE/PATCH,
// portanto um GET é livre.
$app->get('/telao', [GameController::class, 'telao']);

// ---------------------------------------------------------------------
// API (JSON) — sem CSRF (ignorado para paths /api) e sem
// authRequired/guestOnly: a autenticação é feita manualmente no
// ApiController (sessão + header X-Device-Id).
// ---------------------------------------------------------------------

// Equipes
$app->post('/api/team/login', [ApiController::class, 'teamLogin']);
$app->post('/api/team/logout', [ApiController::class, 'teamLogout']);
$app->get('/api/team/state', [ApiController::class, 'teamState']);
$app->post('/api/team/checkin', [ApiController::class, 'teamCheckin']);
$app->post('/api/team/selfie', [ApiController::class, 'teamSelfie']);
$app->post('/api/team/answer', [ApiController::class, 'teamAnswer']);
$app->get('/api/team/current', [ApiController::class, 'teamCurrent']);
$app->post('/api/team/final-answer', [ApiController::class, 'teamFinalAnswer']);
$app->get('/api/team/points', [ApiController::class, 'teamPoints']);
$app->post('/api/team/messages/read', [ApiController::class, 'teamMessagesRead']);
$app->post('/api/team/location', [ApiController::class, 'teamLocation']);

// Públicas
$app->get('/api', [ApiController::class, 'index']);
$app->get('/api/story', [ApiController::class, 'story']);
$app->get('/api/config', [ApiController::class, 'config']);
$app->get('/api/telao', [ApiController::class, 'telao']);

// Admin (app de gerenciamento)
$app->post('/api/admin/login', [ApiController::class, 'adminLogin']);
$app->post('/api/admin/logout', [ApiController::class, 'adminLogout']);
$app->get('/api/admin/treasures', [ApiController::class, 'adminTreasures']);
$app->post('/api/admin/confirm-coordinate', [ApiController::class, 'adminConfirmCoordinate']);
$app->post('/api/admin/disconnect-all', [ApiController::class, 'adminDisconnectAll']);
$app->get('/api/admin/status', [ApiController::class, 'adminStatus']);
$app->get('/api/admin/game', [ApiController::class, 'adminGame']);
$app->put('/api/admin/game', [ApiController::class, 'adminGameUpdate']);
$app->post('/api/admin/team-points', [ApiController::class, 'adminTeamPoints']);
$app->post('/api/admin/team-message', [ApiController::class, 'adminTeamMessage']);
$app->get('/api/admin/treasures/{id}', [ApiController::class, 'adminTreasureShow']);
$app->put('/api/admin/treasures/{id}', [ApiController::class, 'adminTreasureUpdate']);

// Endpoints legados (compatibilidade com o app antigo)
$app->post('/api/login', [ApiController::class, 'login']);
$app->post('/api/logout', [ApiController::class, 'logout']);
$app->get('/api/me', [ApiController::class, 'me']);
$app->get('/api/tesouros', [ApiController::class, 'treasures']);
$app->post('/api/tesouros/{id}/coordenada', [ApiController::class, 'setCoordinate']);

return $app;