<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Repositories\GameRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\Repositories\TreasureRepository;
use App\Repositories\UserRepository;
use DateTime;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * API JSON do jogo "Caça ao Tesouro" (aplicativo móvel + admin).
 *
 * As rotas /api NÃO passam pelo CSRF (ver middleware 'csrf') nem pelos
 * middleware authRequired/guestOnly: a autenticação é feita manualmente
 * aqui (sessão iniciada pelo middleware 'startSession' + header
 * X-Device-Id no caso das equipes).
 */
final class ApiController
{
    private const SELFIE_DIR = '/public/uploads/selfies';
    private const MAX_SELFIE_BYTES = 5 * 1024 * 1024;

    // ==================================================================
    // Autenticação de EQUIPE
    // ==================================================================

    /**
     * POST /api/team/login
     *
     * Body: { username, password, device_id }
     * device_id: identifica o aparelho (>= 8 chars). É gravado em
     * teams.session_token e deve acompanhar todas as requisições seguintes
     * no header X-Device-Id (conexão única por equipe).
     */
    public function teamLogin(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $username = strtolower(trim((string) ($body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $deviceId = trim((string) ($body['device_id'] ?? ''));

        $team = ($username !== '' && $password !== '')
            ? TeamRepository::findByUsername($username)
            : null;

        if ($team === null || !password_verify($password, (string) $team['password_hash'])) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Usuário ou senha inválidos.',
            ], 401);
        }

        if (strlen($deviceId) < 8) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'O device_id deve ter pelo menos 8 caracteres.',
            ], 400);
        }

        $teamId = (int) $team['id'];
        $currentToken = (string) ($team['session_token'] ?? '');

        // Conexão única: se outro aparelho já está logado com esta equipe,
        // bloqueia (o usuário deve desconectar pelo admin ou esperar).
        if ($currentToken !== '' && $currentToken !== $deviceId) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Outro membro da equipe já está logado no aplicativo.',
            ], 409);
        }

        session_regenerate_id(true);

        // Login de time destrói qualquer sessão de admin anterior.
        unset($_SESSION['user'], $_SESSION['admin']);

        TeamRepository::setSessionToken($teamId, $deviceId);

        $_SESSION['team'] = [
            'id'       => $teamId,
            'name'     => (string) $team['name'],
            'color'    => (string) $team['color'],
            'username' => (string) $team['username'],
        ];

        return $this->json($response, [
            'success' => true,
            'team'    => [
                'id'       => $teamId,
                'name'     => (string) $team['name'],
                'color'    => (string) $team['color'],
                'username' => (string) $team['username'],
                'points'   => (int) $team['points'],
            ],
        ]);
    }

    /**
     * POST /api/team/logout
     *
     * Se o session_token da equipe bater com o X-Device-Id enviado, limpa
     * o token e destrói a sessão.
     */
    public function teamLogout(Request $request, Response $response): Response
    {
        $teamSession = $_SESSION['team'] ?? null;
        $deviceId = trim($request->getHeaderLine('X-Device-Id'));

        if (is_array($teamSession) && isset($teamSession['id']) && $deviceId !== '') {
            $team = TeamRepository::find((int) $teamSession['id']);

            if ($team !== null && (string) ($team['session_token'] ?? '') === $deviceId) {
                TeamRepository::setSessionToken((int) $team['id'], null);
            }
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();

        return $this->json($response, ['success' => true]);
    }

    /**
     * GET /api/team/state
     *
     * Estado completo do jogo para o app da equipe.
     */
    public function teamState(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        $teamId = (int) $team['id'];
        $currentTreasureId = GameRepository::currentTreasureId($team);
        $finalAvailable = GameRepository::finalAvailable($team);

        $currentTreasure = null;

        if ($currentTreasureId !== null) {
            $treasure = TreasureRepository::find($currentTreasureId);

            if ($treasure !== null) {
                $currentTreasure = [
                    'id'           => (int) $treasure['id'],
                    'name'         => (string) $treasure['name'],
                    'clue'         => (string) $treasure['clue'],
                    'has_location' => $this->hasLocation($treasure),
                ];
            }
        }

        $leaderboard = [];

        foreach (TeamRepository::all() as $row) {
            $leaderboard[] = [
                'team'   => [
                    'id'    => (int) $row['id'],
                    'name'  => (string) $row['name'],
                    'color' => (string) $row['color'],
                ],
                'points' => (int) $row['points'],
                'status' => (string) ($row['status'] ?? 'playing'),
            ];
        }

        usort($leaderboard, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        $data = [
            'success' => true,
            'team'    => [
                'points'       => (int) $team['points'],
                'status'       => (string) ($team['status'] ?? 'playing'),
                'current_step' => (int) $team['current_step'],
            ],
            'gameActive'      => (string) SettingsRepository::get('gameActive', '0'),
            'game_status'     => (string) SettingsRepository::get('gameStatus', 'playing'),
            'game_start_date' => (string) SettingsRepository::get('gameStartDate', ''),
            'game_start_time' => (string) SettingsRepository::get('gameStartTime', '08:00'),
            'game_end_time'   => (string) SettingsRepository::get('gameEndTime', '17:00'),
            'story'           => (string) SettingsRepository::get('historyContent', ''),
            'current_treasure'=> $currentTreasure,
            'final_available' => $finalAvailable,
            'leaderboard'     => $leaderboard,
            'messages'        => self::teamMessages($teamId),
        ];

        if ($finalAvailable) {
            $data['final_clue'] = (string) SettingsRepository::get('finalClue', '');
        }

        return $this->json($response, $data);
    }

    /**
     * POST /api/team/checkin
     *
     * Body: { treasure_id, lat, lng, qr_code }
     * Valida GPS (<= 30 m), QR code e ordem de jogo; assinala a charada.
     */
    public function teamCheckin(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        // Regras do jogo (status + horário + data de início).
        $block = $this->gameBlock();

        if ($block !== null) {
            return $this->json($response, ['success' => false] + $block, 400);
        }

        $body = (array) $request->getParsedBody();
        $teamId = (int) $team['id'];

        $treasureId = (int) ($body['treasure_id'] ?? 0);
        $lat = $body['lat'] ?? null;
        $lng = $body['lng'] ?? null;
        $qrCode = trim((string) ($body['qr_code'] ?? ''));

        $treasure = TreasureRepository::find($treasureId);

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        if ((int) $treasure['active'] !== 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Este tesouro ainda não está ativo.',
            ], 400);
        }

        if (!$this->hasLocation($treasure)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Este tesouro ainda não tem coordenada definida.',
            ], 400);
        }

        if ($qrCode === '' || $qrCode !== (string) $treasure['qr_content']) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Código QR inválido.',
            ], 400);
        }

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        $distance = haversine_meters(
            $lat,
            $lng,
            (float) $treasure['lat'],
            (float) $treasure['lng']
        );

        if ($distance > 30) {
            $distanceRounded = (int) round($distance);

            return $this->json($response, [
                'success'    => false,
                'error'      => 'Você está a ' . $distanceRounded . 'm do local. Aproxime-se (máx 30m).',
                'distance_m' => $distanceRounded,
            ], 400);
        }

        // Deve ser o tesouro ATUAL da equipe.
        $currentTreasureId = GameRepository::currentTreasureId($team);

        if ($currentTreasureId === null || $currentTreasureId !== $treasureId) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Este não é o próximo tesouro.',
            ], 400);
        }

        // Check-in idempotente: se já existe com gps_confirmed, devolve o
        // mesmo resultado (re-exibe a charada assinalada).
        $existing = GameRepository::progress($teamId, $treasureId);

        if ($existing !== null && (int) $existing['gps_confirmed'] === 1) {
            return $this->json($response, [
                'success'         => true,
                'message'         => 'Você já fez check-in neste tesouro. A selfie no local é obrigatória antes de responder.',
                'assigned_riddle' => (int) $existing['assigned_riddle'],
                'riddle'          => (string) ((int) $existing['assigned_riddle'] === 1
                    ? $treasure['riddle1']
                    : $treasure['riddle2']),
                'selfie_question' => true,
                'selfie_required' => true,
            ]);
        }

        $assignedRiddle = GameRepository::assignRiddle($teamId, $treasureId);

        // Garante gps_confirmed = 1 (a linha pode ter sido criada pelo
        // assignRiddle).
        $this->updateProgress($teamId, $treasureId, ['gps_confirmed' => 1]);

        $riddle = $assignedRiddle === 1
            ? (string) $treasure['riddle1']
            : (string) $treasure['riddle2'];

        return $this->json($response, [
            'success'         => true,
            'message'         => 'Check-in confirmado! Envie a selfie no local para liberar a charada.',
            'assigned_riddle' => $assignedRiddle,
            'riddle'          => $riddle,
            'selfie_question' => true,
            'selfie_required' => true,
        ]);
    }

    /**
     * POST /api/team/selfie — multipart/form-data
     *
     * Campos: { treasure_id, image (arquivo) }
     * +5 pontos pela selfie no local do tesouro.
     */
    public function teamSelfie(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();
        $teamId = (int) $team['id'];
        $treasureId = (int) ($body['treasure_id'] ?? 0);

        $progress = GameRepository::progress($teamId, $treasureId);

        if ($progress === null || (int) $progress['gps_confirmed'] !== 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Faça o check-in neste tesouro antes de enviar a selfie.',
            ], 400);
        }

        if ((int) $progress['riddle_correct'] === 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Este tesouro já foi resolvido.',
            ], 400);
        }

        if ((int) $progress['selfie_points'] === 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Selfie já enviada para este tesouro.',
            ], 400);
        }

        $files = $request->getUploadedFiles();
        $image = $files['image'] ?? null;

        if (!$image instanceof UploadedFileInterface || $image->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Envie uma imagem válida.',
            ], 400);
        }

        if ($image->getSize() > self::MAX_SELFIE_BYTES) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A imagem deve ter no máximo 5MB.',
            ], 400);
        }

        $clientName = (string) $image->getClientFilename();
        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Formato de imagem inválido (use JPG, PNG ou WEBP).',
            ], 400);
        }

        $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $dir = $projectRoot . self::SELFIE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Não foi possível salvar a imagem no servidor.',
            ], 500);
        }

        $filename = sprintf('%d_%d_%d.%s', $teamId, $treasureId, time(), $extension);
        $filePath = $dir . '/' . $filename;

        try {
            $image->moveTo($filePath);
        } catch (Throwable $e) {
            error_log('[Caça ao Tesouro] Falha ao salvar selfie: ' . $e->getMessage());

            return $this->json($response, [
                'success' => false,
                'error'   => 'Não foi possível salvar a imagem no servidor.',
            ], 500);
        }

        $webPath = '/uploads/selfies/' . $filename;

        $this->updateProgress($teamId, $treasureId, [
            'selfie_path'   => $webPath,
            'selfie_points' => 1,
        ]);

        TeamRepository::addPoints($teamId, 5);
        GameRepository::logPoints($teamId, 5, 'selfie');

        return $this->json($response, [
            'success' => true,
            'message' => '+5 pontos pela selfie!',
            'points'  => (int) TeamRepository::find($teamId)['points'],
            'selfie_path' => $webPath,
        ]);
    }

    /**
     * POST /api/team/answer
     *
     * Body: { treasure_id, answer }
     * Acertou: +20 e avança para o próximo tesouro.
     * Errou: -5 e permanece travado (pode tentar de novo).
     */
    public function teamAnswer(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        // Apenas status do jogo (horário/data não bloqueiam respostas).
        $block = $this->gameBlock(false);

        if ($block !== null) {
            return $this->json($response, ['success' => false] + $block, 400);
        }

        $body = (array) $request->getParsedBody();
        $teamId = (int) $team['id'];
        $treasureId = (int) ($body['treasure_id'] ?? 0);
        $answer = trim((string) ($body['answer'] ?? ''));

        $treasure = TreasureRepository::find($treasureId);

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        $progress = GameRepository::progress($teamId, $treasureId);

        if ($progress === null || (int) $progress['gps_confirmed'] !== 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Faça o check-in neste tesouro antes de responder.',
            ], 400);
        }

        // Selfie OBRIGATÓRIA: só é possível responder a charada depois de
        // enviada a foto no local (selfie_points = 1). Nada de pontos é
        // movimentado nem a resposta é avaliada enquanto não houver selfie.
        if ((int) $progress['selfie_points'] !== 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A selfie é obrigatória no local antes de responder a charada.',
                'code'    => 'selfie_required',
            ], 400);
        }

        if ((int) $progress['riddle_correct'] === 1) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Este tesouro já foi resolvido.',
            ], 400);
        }

        $assignedRiddle = (int) ($progress['assigned_riddle'] ?? 0);
        $correctAnswer = $assignedRiddle === 1
            ? (string) $treasure['answer1']
            : (string) $treasure['answer2'];

        $points = (int) $team['points'];

        // Resposta correta (trim + case-insensitive).
        if (strcasecmp($answer, $correctAnswer) === 0) {
            $this->updateProgress($teamId, $treasureId, [
                'riddle_correct' => 1,
                'found_at'       => date('Y-m-d H:i:s'),
                'points_awarded' => 1,
            ]);

            TeamRepository::addPoints($teamId, 20);
            GameRepository::logPoints($teamId, 20, 'tesouro');

            $newStep = (int) $team['current_step'] + 1;
            TeamRepository::updateGameState($teamId, ['current_step' => $newStep]);

            // Próximo tesouro (considerando a nova etapa) ou desafio final.
            $order = GameRepository::treasureOrderForTeam($team);
            $nextId = $order[$newStep] ?? null;
            $next = null;

            if ($nextId !== null) {
                $nextTreasure = TreasureRepository::find($nextId);

                if ($nextTreasure !== null) {
                    $next = [
                        'id'           => (int) $nextTreasure['id'],
                        'name'         => (string) $nextTreasure['name'],
                        'clue'         => (string) $nextTreasure['clue'],
                        'has_location' => $this->hasLocation($nextTreasure),
                    ];
                }
            }

            $finalAvailable = $newStep >= count($order);

            return $this->json($response, [
                'success' => true,
                'correct' => true,
                'message' => 'Resposta correta! +20 pontos.',
                'points'  => $points + 20,
                'next'    => [
                    'treasure'        => $next,
                    'final_available' => $finalAvailable,
                ],
            ]);
        }

        // Resposta incorreta.
        $attempts = (int) $progress['attempts'] + 1;

        $this->updateProgress($teamId, $treasureId, ['attempts' => $attempts]);

        TeamRepository::addPoints($teamId, -5);
        GameRepository::logPoints($teamId, -5, 'erro charada');

        return $this->json($response, [
            'success'  => true,
            'correct'  => false,
            'message'  => 'Resposta incorreta. -5 pontos.',
            'points'   => $points - 5,
            'attempts' => $attempts,
        ]);
    }

    /**
     * GET /api/team/current
     *
     * Tesouro atual da equipe (id, name, clue) ou sinalização do desafio
     * final.
     */
    public function teamCurrent(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        $currentTreasureId = GameRepository::currentTreasureId($team);
        $finalAvailable = GameRepository::finalAvailable($team);

        $treasure = null;

        if ($currentTreasureId !== null) {
            $row = TreasureRepository::find($currentTreasureId);

            if ($row !== null) {
                $treasure = [
                    'id'           => (int) $row['id'],
                    'name'         => (string) $row['name'],
                    'clue'         => (string) $row['clue'],
                    'has_location' => $this->hasLocation($row),
                ];
            }
        }

        return $this->json($response, [
            'success'         => true,
            'treasure'        => $treasure,
            'final_available' => $finalAvailable,
        ]);
    }

    /**
     * POST /api/team/final-answer
     *
     * Body: { answer }
     * Senha final correta: +100 e encerra a partida (primeira equipe a
     * terminar define a vencedora). Errada: -20.
     */
    public function teamFinalAnswer(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        // Apenas status do jogo (paused/finished bloqueiam o desafio final).
        $block = $this->gameBlock(false);

        if ($block !== null) {
            return $this->json($response, ['success' => false] + $block, 400);
        }

        if (!GameRepository::finalAvailable($team)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'O desafio final ainda não está disponível.',
            ], 400);
        }

        $body = (array) $request->getParsedBody();
        $teamId = (int) $team['id'];
        $answer = trim((string) ($body['answer'] ?? ''));
        $finalAnswer = trim((string) SettingsRepository::get('finalAnswer', ''));

        $points = (int) $team['points'];

        if (strcasecmp($answer, $finalAnswer) === 0) {
            TeamRepository::addPoints($teamId, 100);
            TeamRepository::markFinished($teamId, date('Y-m-d H:i:s'));
            GameRepository::logPoints($teamId, 100, 'desafio final');

            $winner = null;

            // A primeira equipe a terminar encerra a partida.
            if (SettingsRepository::get('gameActive', '0') === '1') {
                SettingsRepository::update([
                    'gameActive'   => '0',
                    'winnerTeamId' => (string) $teamId,
                ]);

                $winner = [
                    'id'    => $teamId,
                    'name'  => (string) $team['name'],
                    'color' => (string) $team['color'],
                ];
            }

            return $this->json($response, [
                'success' => true,
                'correct' => true,
                'message' => 'Parabéns! +100 pontos. A caça terminou!',
                'points'  => $points + 100,
                'winner'  => $winner,
            ]);
        }

        TeamRepository::addPoints($teamId, -20);
        GameRepository::logPoints($teamId, -20, 'erro desafio final');

        return $this->json($response, [
            'success' => false,
            'correct' => false,
            'message' => 'Senha incorreta. -20 pontos.',
            'points'  => $points - 20,
        ]);
    }

    /**
     * GET /api/team/points
     */
    public function teamPoints(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        return $this->json($response, [
            'success' => true,
            'points'  => (int) $team['points'],
            'status'  => (string) ($team['status'] ?? 'playing'),
        ]);
    }

    /**
     * POST /api/team/messages/read
     *
     * Body: { ids: [int] }
     * Marca como lidas (read_at = NOW()) as mensagens da equipe cujos ids
     * foram informados. Só afeta mensagens ainda não lidas.
     */
    public function teamMessagesRead(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();
        $ids = $body['ids'] ?? null;

        if (!is_array($ids)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Lista de ids inválida.',
            ], 400);
        }

        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn ($id): bool => is_numeric($id) && (int) $id > 0
        )));

        if ($ids === []) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Nenhuma mensagem informada.',
            ], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            'UPDATE team_messages SET read_at = ? '
            . 'WHERE team_id = ? AND read_at IS NULL AND id IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge(
            [date('Y-m-d H:i:s'), (int) $team['id']],
            array_map('intval', $ids)
        ));

        return $this->json($response, ['success' => true]);
    }

    /**
     * POST /api/team/location
     *
     * Body: { lat, lng, accuracy? }
     * Registra a posição GPS da equipe. O app envia a cada ~5s, sem
     * throttle. A ÚLTIMA linha de cada equipe alimenta o telão
     * (/api/telao).
     */
    public function teamLocation(Request $request, Response $response): Response
    {
        $team = $this->requireTeam($request);

        if ($team === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();
        $lat = $body['lat'] ?? null;
        $lng = $body['lng'] ?? null;
        $accuracy = $body['accuracy'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        $stmt = Database::get()->prepare(
            'INSERT INTO team_locations (team_id, lat, lng, accuracy, created_at) '
            . 'VALUES (:team_id, :lat, :lng, :accuracy, :created_at)'
        );
        $stmt->execute([
            ':team_id'    => (int) $team['id'],
            ':lat'        => $lat,
            ':lng'        => $lng,
            ':accuracy'   => is_numeric($accuracy) ? (float) $accuracy : null,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->json($response, ['success' => true]);
    }

    // ==================================================================
    // Rotas públicas
    // ==================================================================

    /**
     * GET /api/telao — dados públicos do telão (SEM autenticação).
     *
     * Equipes (pontos/status/última localização), tesouros com
     * coordenada definida (e quem já os encontrou) e as 6 selfies mais
     * recentes.
     */
    public function telao(Request $request, Response $response): Response
    {
        $teams = [];

        foreach (TeamRepository::all() as $row) {
            $teamId = (int) $row['id'];

            $lastLocation = self::lastLocation($teamId);

            // Online = enviou localização nos últimos 15s (o app envia a cada 5s).
            $online = false;

            if ($lastLocation !== null && !empty($lastLocation['updated_at'])) {
                $online = (strtotime((string) $lastLocation['updated_at']) + 15) >= time();
            }

            $teams[] = [
                'id'            => $teamId,
                'name'          => (string) $row['name'],
                'color'         => (string) $row['color'],
                'points'        => (int) $row['points'],
                'status'        => (string) ($row['status'] ?? 'playing'),
                'found_count'   => GameRepository::foundCount($teamId),
                'online'        => $online,
                'last_location' => $lastLocation,
            ];
        }

        // Mapa treasure_id => cores que já encontraram (found_at != null).
        $found = [];

        foreach (self::allFoundProgress() as $progress) {
            $treasureId = (int) $progress['treasure_id'];
            $color = (string) $progress['color'];

            if ($color !== '') {
                $found[$treasureId][$color] = true;
            }
        }

        $treasures = [];

        foreach (TreasureRepository::all() as $row) {
            if (!self::hasLocation($row)) {
                continue; // Sem coordenada: o mapa não tem onde colocar.
            }

            $treasureId = (int) $row['id'];
            $foundByPreta = isset($found[$treasureId]['preta']);
            $foundByLaranja = isset($found[$treasureId]['laranja']);

            $treasures[] = [
                'id'               => $treasureId,
                'code'             => (string) $row['code'],
                'name'             => (string) $row['name'],
                'lat'              => self::latOrNull($row),
                'lng'              => self::lngOrNull($row),
                'has_coord'        => true,
                'found_by_preta'   => $foundByPreta,
                'found_by_laranja' => $foundByLaranja,
                'finalized'        => $foundByPreta && $foundByLaranja,
            ];
        }

        return $this->json($response, [
            'success'   => true,
            'teams'     => $teams,
            'treasures' => $treasures,
            'selfies'   => self::recentSelfies(6),
        ]);
    }

    /**
     * GET /api/story — história do jogo (pública).
     */
    public function story(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'success' => true,
            'story'   => (string) SettingsRepository::get('historyContent', ''),
        ]);
    }

    // ==================================================================
    // Autenticação de ADMIN (API)
    // ==================================================================

    /**
     * POST /api/admin/login
     *
     * Body: { username, password }. Credenciais em settings
     * (adminUsername/adminPassword).
     */
    public function adminLogin(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $username = strtolower(trim((string) ($body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $expectedUsername = strtolower(trim((string) SettingsRepository::get('adminUsername', 'admin')));
        $expectedPassword = (string) SettingsRepository::get('adminPassword', 'admin1234');

        if ($username !== $expectedUsername || !hash_equals($expectedPassword, $password)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Usuário ou senha inválidos.',
            ], 401);
        }

        session_regenerate_id(true);

        unset($_SESSION['user'], $_SESSION['team']);

        $_SESSION['admin'] = ['role' => 'admin'];

        return $this->json($response, [
            'success' => true,
            'user'    => ['role' => 'admin'],
        ]);
    }

    /**
     * POST /api/admin/logout
     */
    public function adminLogout(Request $request, Response $response): Response
    {
        unset($_SESSION['admin']);

        return $this->json($response, ['success' => true]);
    }

    /**
     * GET /api/admin/treasures
     */
    public function adminTreasures(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $treasures = array_map(static function (array $row): array {
            return [
                'id'         => (int) $row['id'],
                'code'       => (string) $row['code'],
                'name'       => (string) $row['name'],
                'lat'        => self::latOrNull($row),
                'lng'        => self::lngOrNull($row),
                'has_coord'  => self::hasLocation($row),
                'active'     => (int) $row['active'],
                'qr_content' => (string) $row['qr_content'],
            ];
        }, TreasureRepository::all());

        return $this->json($response, [
            'success'   => true,
            'treasures' => $treasures,
        ]);
    }

    /**
     * POST /api/admin/confirm-coordinate
     *
     * Body: { treasure_id, lat, lng, qr_code }
     * Confirma o local real do tesouro e o ativa.
     */
    public function adminConfirmCoordinate(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();

        $treasureId = (int) ($body['treasure_id'] ?? 0);
        $lat = $body['lat'] ?? null;
        $lng = $body['lng'] ?? null;
        $qrCode = trim((string) ($body['qr_code'] ?? ''));

        $treasure = TreasureRepository::find($treasureId);

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        if ($qrCode === '' || $qrCode !== (string) $treasure['qr_content']) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Código QR inválido.',
            ], 400);
        }

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        TreasureRepository::update($treasureId, [
            'lat'    => $lat,
            'lng'    => $lng,
            'active' => 1,
        ]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Coordenada confirmada. Tesouro ativado!',
            'lat'     => $lat,
            'lng'     => $lng,
        ]);
    }

    /**
     * POST /api/admin/disconnect-all
     */
    public function adminDisconnectAll(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        TeamRepository::clearAllSessions();

        return $this->json($response, [
            'success' => true,
            'message' => 'Todas as equipes desconectadas.',
        ]);
    }

    /**
     * GET /api/admin/status
     */
    public function adminStatus(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $teams = TeamRepository::all();
        $teamsOut = [];

        foreach ($teams as $team) {
            $teamsOut[] = [
                'name'        => (string) $team['name'],
                'color'       => (string) $team['color'],
                'points'      => (int) $team['points'],
                'status'      => (string) ($team['status'] ?? 'playing'),
                'finished_at' => $team['finished_at'] ?? null,
                'found_count' => GameRepository::foundCount((int) $team['id']),
            ];
        }

        // Mapa team_id => cor para o progresso.
        $teamColors = [];

        foreach ($teams as $team) {
            $teamColors[(int) $team['id']] = (string) $team['color'];
        }

        $progressOut = [];

        foreach (TreasureRepository::all() as $treasure) {
            $rows = GameRepository::progressForTreasure((int) $treasure['id']);

            $byColor = ['preta' => ['found_at' => null], 'laranja' => ['found_at' => null]];

            foreach ($rows as $row) {
                $color = $teamColors[(int) $row['team_id']] ?? null;

                if ($color !== null && array_key_exists($color, $byColor)) {
                    $byColor[$color]['found_at'] = $row['found_at'] ?? null;
                }
            }

            $progressOut[] = [
                'treasure' => [
                    'id'   => (int) $treasure['id'],
                    'code' => (string) $treasure['code'],
                    'name' => (string) $treasure['name'],
                ],
                'preta'   => $byColor['preta'],
                'laranja' => $byColor['laranja'],
            ];
        }

        return $this->json($response, [
            'success' => true,
            'game'    => [
                'gameStatus'   => (string) SettingsRepository::get('gameStatus', 'playing'),
                'gameActive'   => (string) SettingsRepository::get('gameActive', '0'),
                'winnerTeamId' => (string) SettingsRepository::get('winnerTeamId', ''),
            ],
            'teams'             => $teamsOut,
            'treasures_progress'=> $progressOut,
        ]);
    }

    /**
     * GET /api/admin/game
     *
     * Estado geral do jogo (settings de controle do enforcement).
     */
    public function adminGame(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        return $this->json($response, [
            'success' => true,
            'game'    => $this->gamePayload(),
        ]);
    }

    /**
     * PUT /api/admin/game
     *
     * Body (todos opcionais): { status?, start_date?, start_time?, end_time? }
     * - status: 'playing' | 'paused' | 'finished'
     * - start_date: '' ou AAAA-MM-DD válido (vazio = sem restrição)
     * - start_time/end_time: '' ou HH:MM
     */
    public function adminGameUpdate(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];

        if (array_key_exists('status', $body)) {
            $status = (string) $body['status'];

            if (!in_array($status, ['playing', 'paused', 'finished'], true)) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Status inválido.',
                ], 400);
            }

            $updates['gameStatus'] = $status;
        }

        if (array_key_exists('start_date', $body)) {
            $startDate = trim((string) $body['start_date']);

            if (!$this->isValidDate($startDate)) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Data de início inválida (use AAAA-MM-DD ou vazio).',
                ], 400);
            }

            $updates['gameStartDate'] = $startDate;
        }

        if (array_key_exists('start_time', $body)) {
            $startTime = trim((string) $body['start_time']);

            if (!$this->isValidTime($startTime)) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Horário de início inválido (use HH:MM ou vazio).',
                ], 400);
            }

            $updates['gameStartTime'] = $startTime;
        }

        if (array_key_exists('end_time', $body)) {
            $endTime = trim((string) $body['end_time']);

            if (!$this->isValidTime($endTime)) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Horário de término inválido (use HH:MM ou vazio).',
                ], 400);
            }

            $updates['gameEndTime'] = $endTime;
        }

        if ($updates !== []) {
            SettingsRepository::update($updates);
        }

        return $this->json($response, [
            'success' => true,
            'game'    => $this->gamePayload(),
        ]);
    }

    /**
     * POST /api/admin/team-points
     *
     * Body: { team_id, delta, reason? }
     * Ajusta manualmente os pontos da equipe (mínimo 0) e registra o
     * histórico em points_log.
     */
    public function adminTeamPoints(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();

        $teamId = (int) ($body['team_id'] ?? 0);
        $team = TeamRepository::find($teamId);

        if ($team === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Equipe não encontrada.',
            ], 404);
        }

        $delta = $body['delta'] ?? null;

        if (!is_numeric($delta) || (float) $delta != (int) $delta || (int) $delta === 0) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Delta inválido.',
            ], 400);
        }

        $delta = (int) $delta;
        $reason = trim((string) ($body['reason'] ?? ''));

        $points = max(0, (int) $team['points'] + $delta);

        TeamRepository::updateGameState($teamId, ['points' => $points]);
        GameRepository::logPoints($teamId, $delta, $reason !== '' ? $reason : 'ajuste do admin');

        return $this->json($response, [
            'success' => true,
            'points'  => $points,
        ]);
    }

    /**
     * POST /api/admin/team-message
     *
     * Body: { team_id, message }
     * Envia uma mensagem à equipe (fica pendente até o app marcá-la como
     * lida em /api/team/messages/read).
     */
    public function adminTeamMessage(Request $request, Response $response): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $body = (array) $request->getParsedBody();

        $teamId = (int) ($body['team_id'] ?? 0);
        $team = TeamRepository::find($teamId);

        if ($team === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Equipe não encontrada.',
            ], 404);
        }

        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '' || mb_strlen($message) > 500) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A mensagem deve ter de 1 a 500 caracteres.',
            ], 400);
        }

        $stmt = Database::get()->prepare(
            'INSERT INTO team_messages (team_id, message, read_at, created_at) '
            . 'VALUES (:team_id, :message, NULL, :created_at)'
        );
        $stmt->execute([
            ':team_id'    => $teamId,
            ':message'    => $message,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Mensagem enviada.',
        ]);
    }

    /**
     * GET /api/admin/treasures/{id}
     *
     * Detalhe completo de um tesouro (para edição).
     */
    public function adminTreasureShow(Request $request, Response $response, array $args): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $treasure = TreasureRepository::find((int) ($args['id'] ?? 0));

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        return $this->json($response, [
            'success'  => true,
            'treasure' => $this->treasurePayload($treasure),
        ]);
    }

    /**
     * PUT /api/admin/treasures/{id}
     *
     * Body: { name, description, clue, riddle1, answer1, riddle2, answer2 }
     * Atualiza o conteúdo textual do tesouro. Mantém code, qr_content,
     * qr_svg_path, lat/lng, active e sort_order.
     */
    public function adminTreasureUpdate(Request $request, Response $response, array $args): Response
    {
        if ($this->requireAdmin() === null) {
            return $this->unauthorized($response);
        }

        $treasure = TreasureRepository::find((int) ($args['id'] ?? 0));

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $clue = trim((string) ($body['clue'] ?? ''));
        $riddle1 = trim((string) ($body['riddle1'] ?? ''));
        $riddle2 = trim((string) ($body['riddle2'] ?? ''));
        $answer1 = trim((string) ($body['answer1'] ?? ''));
        $answer2 = trim((string) ($body['answer2'] ?? ''));

        if ($name === '') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'O nome do tesouro é obrigatório.',
            ], 400);
        }

        if ($clue === '') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A dica do tesouro é obrigatória.',
            ], 400);
        }

        if ($riddle1 === '') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A charada 1 é obrigatória.',
            ], 400);
        }

        if ($riddle2 === '') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A charada 2 é obrigatória.',
            ], 400);
        }

        if (!preg_match('/^\d{4,8}$/', $answer1)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A resposta 1 deve conter de 4 a 8 dígitos.',
            ], 400);
        }

        if (!preg_match('/^\d{4,8}$/', $answer2)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'A resposta 2 deve conter de 4 a 8 dígitos.',
            ], 400);
        }

        TreasureRepository::update((int) $treasure['id'], [
            'name'        => $name,
            'description' => $description,
            'clue'        => $clue,
            'riddle1'     => $riddle1,
            'answer1'     => $answer1,
            'riddle2'     => $riddle2,
            'answer2'     => $answer2,
        ]);

        $updated = TreasureRepository::find((int) $treasure['id']);

        return $this->json($response, [
            'success'  => true,
            'treasure' => $this->treasurePayload($updated),
        ]);
    }

    // ==================================================================
    // Endpoints legados (compatibilidade com o app antigo)
    // ==================================================================

    /**
     * POST /api/login — usuário do painel (tabela users).
     */
    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $username = strtolower(trim((string) ($body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $user = ($username !== '' && $password !== '')
            ? UserRepository::findByUsername($username)
            : null;

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Usuário ou senha inválidos.',
            ], 401);
        }

        session_regenerate_id(true);

        $userData = [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
            'role'     => (string) ($user['role'] ?? 'admin'),
        ];

        $_SESSION['user'] = $userData;

        return $this->json($response, [
            'success' => true,
            'user'    => $userData,
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();

        return $this->json($response, ['success' => true]);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request, Response $response): Response
    {
        $user = $this->apiUser();

        if ($user === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Não autenticado.',
            ], 401);
        }

        return $this->json($response, [
            'success' => true,
            'user'    => $user,
        ]);
    }

    /**
     * GET /api/tesouros — lista pública de tesouros (sem segredos).
     */
    public function treasures(Request $request, Response $response): Response
    {
        if ($this->apiUser() === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Não autenticado.',
            ], 401);
        }

        $treasures = array_map(static function (array $row): array {
            return [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'description' => (string) $row['description'],
                'lat'         => self::latOrNull($row),
                'lng'         => self::lngOrNull($row),
                'has_coord'   => self::hasLocation($row),
            ];
        }, TreasureRepository::all());

        return $this->json($response, [
            'success'   => true,
            'treasures' => $treasures,
        ]);
    }

    /**
     * POST /api/tesouros/{id}/coordenada — define coordenada (legado).
     */
    public function setCoordinate(Request $request, Response $response, array $args): Response
    {
        $user = $this->apiUser();

        if ($user === null || (string) ($user['role'] ?? '') !== 'admin') {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Apenas administradores podem definir coordenadas.',
            ], 401);
        }

        $id = (int) ($args['id'] ?? 0);
        $treasure = TreasureRepository::find($id);

        if ($treasure === null) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Tesouro não encontrado.',
            ], 404);
        }

        $body = (array) $request->getParsedBody();
        $lat = $body['lat'] ?? null;
        $lng = $body['lng'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Coordenadas inválidas.',
            ], 400);
        }

        TreasureRepository::update($id, ['lat' => $lat, 'lng' => $lng]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Coordenada definida.',
            'lat'     => $lat,
            'lng'     => $lng,
        ]);
    }

    /**
     * GET /api — índice/informações da API (health check).
     */
    public function index(Request $request, Response $response): Response
    {
        $devMode = (string) SettingsRepository::get('apiDevMode', '0') === '1'
            || app_config('app.env', 'prod') === 'dev';

        return $this->json($response, [
            'success' => true,
            'name'    => (string) app_config('app.name', 'Caça ao Tesouro') . ' API',
            'version' => '1.0',
            'env'     => (string) app_config('app.env', 'prod'),
            'devMode' => (bool) $devMode,
            'time'    => date('Y-m-d H:i:s'),
            'endpoints' => [
                'health'    => '/api/config',
                'story'     => '/api/story',
                'team'      => '/api/team/login',
                'admin'     => '/api/admin/login',
            ],
        ]);
    }

    /**
     * GET /api/config — configuração pública do app.
     */
    public function config(Request $request, Response $response): Response
    {
        $apiBaseUrl = trim((string) SettingsRepository::get('apiBaseUrl', ''));
        $appUrl = (string) app_config('app.url', '');

        $devMode = (string) SettingsRepository::get('apiDevMode', '0') === '1'
            || app_config('app.env', 'prod') === 'dev';

        return $this->json($response, [
            'success' => true,
            'config'  => [
                'appName'    => (string) app_config('app.name', 'Caça ao Tesouro'),
                'apiBaseUrl' => $apiBaseUrl !== '' ? $apiBaseUrl : $appUrl,
                'devMode'    => (bool) $devMode,
            ],
        ]);
    }

    // ==================================================================
    // Privados
    // ==================================================================

    /**
     * Verifica as regras de jogo (settings) e devolve o bloqueio, se
     * houver, como array { code, error } — ou null quando o jogo está
     * liberado.
     *
     * Ordem de checagem:
     *  1. status 'paused'  -> game_paused
     *  2. status 'finished'-> game_finished
     *  3. data de início (gameStartDate) no futuro -> game_not_started
     *  4. horário fora de [gameStartTime, gameEndTime] -> game_closed
     *
     * @param bool $checkTime Quando false, ignora data/horário (usado em
     *                        teamAnswer e teamFinalAnswer: só status).
     *
     * @return array{code: string, error: string}|null
     */
    private function gameBlock(bool $checkTime = true): ?array
    {
        $status = (string) SettingsRepository::get('gameStatus', 'playing');

        if ($status === 'paused') {
            return ['code' => 'game_paused', 'error' => 'O jogo está pausado.'];
        }

        if ($status === 'finished') {
            return ['code' => 'game_finished', 'error' => 'O jogo terminou.'];
        }

        if (!$checkTime) {
            return null;
        }

        $startDate = trim((string) SettingsRepository::get('gameStartDate', ''));

        if ($startDate !== '' && date('Y-m-d') < $startDate) {
            $formatted = date('d/m/Y', strtotime($startDate));

            return [
                'code'  => 'game_not_started',
                'error' => 'O jogo começa em ' . $formatted . '.',
            ];
        }

        $startTime = trim((string) SettingsRepository::get('gameStartTime', ''));
        $endTime = trim((string) SettingsRepository::get('gameEndTime', ''));

        if ($startTime !== '' && $endTime !== '') {
            $now = strtotime(date('H:i'));
            $start = strtotime($startTime);
            $end = strtotime($endTime);

            // Intervalo que cruza a meia-noite (start > end) é tratado
            // como faixa circular.
            $inside = $start <= $end
                ? ($now >= $start && $now <= $end)
                : ($now >= $start || $now <= $end);

            if (!$inside) {
                return [
                    'code'  => 'game_closed',
                    'error' => 'Os tesouros podem ser encontrados entre '
                        . $startTime . ' e ' . $endTime . '.',
                ];
            }
        }

        return null;
    }

    /**
     * Payload de estado do jogo (GET/PUT /api/admin/game).
     *
     * @return array<string, string>
     */
    private function gamePayload(): array
    {
        return [
            'status'         => (string) SettingsRepository::get('gameStatus', 'playing'),
            'start_date'     => (string) SettingsRepository::get('gameStartDate', ''),
            'start_time'     => (string) SettingsRepository::get('gameStartTime', '08:00'),
            'end_time'       => (string) SettingsRepository::get('gameEndTime', '17:00'),
            'active'         => (string) SettingsRepository::get('gameActive', '0'),
            'winner_team_id' => (string) SettingsRepository::get('winnerTeamId', ''),
        ];
    }

    /**
     * Payload completo de um tesouro (GET/PUT /api/admin/treasures/{id}).
     *
     * @param array<string, mixed> $treasure Linha da tabela treasures
     *
     * @return array<string, mixed>
     */
    private function treasurePayload(array $treasure): array
    {
        return [
            'id'          => (int) $treasure['id'],
            'code'        => (string) $treasure['code'],
            'name'        => (string) $treasure['name'],
            'description' => (string) $treasure['description'],
            'clue'        => (string) $treasure['clue'],
            'riddle1'     => (string) $treasure['riddle1'],
            'answer1'     => (string) $treasure['answer1'],
            'riddle2'     => (string) $treasure['riddle2'],
            'answer2'     => (string) $treasure['answer2'],
            'lat'         => self::latOrNull($treasure),
            'lng'         => self::lngOrNull($treasure),
            'active'      => (int) $treasure['active'],
            'qr_svg_path' => (string) $treasure['qr_svg_path'],
        ];
    }

    /**
     * Mensagens NÃO lidas de uma equipe (read_at IS NULL), na ordem de
     * criação, limitadas a $limit registros.
     *
     * @return array<int, array{id: int, message: string, created_at: string}>
     */
    private static function teamMessages(int $teamId, int $limit = 50): array
    {
        $stmt = Database::get()->prepare(
            'SELECT id, message, created_at FROM team_messages '
            . 'WHERE team_id = :team_id AND read_at IS NULL '
            . 'ORDER BY created_at ASC, id ASC '
            . 'LIMIT ' . (int) $limit
        );
        $stmt->execute([':team_id' => $teamId]);

        return array_map(static function (array $row): array {
            return [
                'id'         => (int) $row['id'],
                'message'    => (string) $row['message'],
                'created_at' => (string) $row['created_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Valida data no formato AAAA-MM-DD (ou vazio, aceito).
     */
    private function isValidDate(string $date): bool
    {
        if ($date === '') {
            return true;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Valida horário no formato HH:MM (ou vazio, aceito).
     */
    private function isValidTime(string $time): bool
    {
        if ($time === '') {
            return true;
        }

        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }

    /**
     * Última localização registrada de uma equipe (a mais recente), ou
     * null quando ainda não há nenhum registro.
     *
     * @return array{lat: float, lng: float, updated_at: string}|null
     */
    private static function lastLocation(int $teamId): ?array
    {
        $stmt = Database::get()->prepare(
            'SELECT lat, lng, created_at FROM team_locations '
            . 'WHERE team_id = :team_id '
            . 'ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':team_id' => $teamId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'lat'        => (float) $row['lat'],
            'lng'        => (float) $row['lng'],
            'updated_at' => (string) $row['created_at'],
        ];
    }

    /**
     * Progresso ENCONTRADO (found_at != null) de todas as equipes, com
     * a cor de cada equipe — usado pelo telão para montar
     * found_by_preta/found_by_laranja/finalized.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function allFoundProgress(): array
    {
        return Database::get()
            ->query(
                'SELECT p.treasure_id, t.color '
                . 'FROM team_treasure_progress p '
                . 'JOIN teams t ON t.id = p.team_id '
                . 'WHERE p.found_at IS NOT NULL'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Selfies mais recentes (selfie_path != ''), com nome/cor da equipe
     * e nome do tesouro.
     *
     * @return array<int, array<string, string>>
     */
    private static function recentSelfies(int $limit): array
    {
        $stmt = Database::get()->prepare(
            'SELECT t.color AS team_color, t.name AS team_name, '
            . 'tr.name AS treasure_name, p.selfie_path, p.found_at '
            . 'FROM team_treasure_progress p '
            . 'JOIN teams t ON t.id = p.team_id '
            . 'JOIN treasures tr ON tr.id = p.treasure_id '
            . 'WHERE p.selfie_path IS NOT NULL AND p.selfie_path != \'\' '
            . 'AND p.found_at IS NOT NULL '
            . 'ORDER BY p.found_at DESC, p.id DESC '
            . 'LIMIT ' . (int) $limit
        );
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'team_color'    => (string) $row['team_color'],
                'team_name'     => (string) $row['team_name'],
                'treasure_name' => (string) $row['treasure_name'],
                'image_path'    => (string) $row['selfie_path'],
                'found_at'      => (string) $row['found_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Valida a autenticação de EQUIPE: sessão com time + header X-Device-Id
     * batendo com teams.session_token.
     *
     * @return array<string, mixed>|null Linha atualizada da tabela teams
     */
    private function requireTeam(Request $request): ?array
    {
        $teamSession = $_SESSION['team'] ?? null;

        if (!is_array($teamSession) || !isset($teamSession['id'])) {
            return null;
        }

        $deviceId = trim($request->getHeaderLine('X-Device-Id'));

        if ($deviceId === '') {
            return null;
        }

        $team = TeamRepository::find((int) $teamSession['id']);

        if ($team === null || (string) ($team['session_token'] ?? '') !== $deviceId) {
            return null;
        }

        return $team;
    }

    /**
     * Admin autenticado na API (sessão $_SESSION['admin']), ou null.
     *
     * @return array<string, mixed>|null
     */
    private function requireAdmin(): ?array
    {
        $admin = $_SESSION['admin'] ?? null;

        return is_array($admin) ? $admin : null;
    }

    /**
     * Usuário legado autenticado (sessão $_SESSION['user']), ou null.
     *
     * @return array<string, mixed>|null
     */
    private function apiUser(): ?array
    {
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    /**
     * Upsert de uma linha de team_treasure_progress.
     *
     * @param array<string, mixed> $fields Campos a gravar (ex.:
     *                                     gps_confirmed, selfie_path, ...)
     */
    private function updateProgress(int $teamId, int $treasureId, array $fields): void
    {
        $pdo = \App\Database::get();
        $driver = app_config('db.driver', 'sqlite');
        $now = date('Y-m-d H:i:s');

        $params = [
            ':team_id'    => $teamId,
            ':treasure_id'=> $treasureId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        $bind = static function (string $name, $value) use (&$params): void {
            if ($value === null) {
                $params[$name] = null;
            } elseif (is_int($value)) {
                $params[$name] = $value;
            } elseif (is_float($value)) {
                $params[$name] = $value;
            } else {
                $params[$name] = (string) $value;
            }
        };

        $insertColumns = [];
        $insertParams = [];
        $updateSets = [];

        foreach ($fields as $column => $value) {
            // Placeholders distintos: <col> (INSERT) e <col>_u (ON DUPLICATE).
            // Com prepared statements nativos (sem emulação), um mesmo nome de
            // parâmetro não pode aparecer mais de uma vez na query.
            $insertColumns[] = $column;
            $insertParams[] = ":$column";
            $updateSets[] = "$column = :{$column}_u";

            $bind(":$column", $value);
            $bind(":{$column}_u", $value);
        }

        if ($driver === 'mysql') {
            $sql = 'INSERT INTO team_treasure_progress '
                . '(team_id, treasure_id, created_at, updated_at'
                . (count($insertColumns) > 0 ? ', ' . implode(', ', $insertColumns) : '')
                . ') VALUES (:team_id, :treasure_id, :created_at, :updated_at'
                . (count($insertParams) > 0 ? ', ' . implode(', ', $insertParams) : '')
                . ') ON DUPLICATE KEY UPDATE '
                . implode(', ', $updateSets) . ', updated_at = :updated_at_u';
            $params[':updated_at_u'] = $now;
        } else {
            $sql = 'INSERT INTO team_treasure_progress '
                . '(team_id, treasure_id, created_at, updated_at'
                . (count($insertColumns) > 0 ? ', ' . implode(', ', $insertColumns) : '')
                . ') VALUES (:team_id, :treasure_id, :created_at, :updated_at'
                . (count($insertParams) > 0 ? ', ' . implode(', ', $insertParams) : '')
                . ') ON CONFLICT(team_id, treasure_id) DO UPDATE SET '
                . implode(', ', $updateSets) . ', updated_at = :updated_at_u';
            $params[':updated_at_u'] = $now;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * lat/lng do tesouro como float (ou null).
     */
    private static function latOrNull(array $treasure): ?float
    {
        $value = $treasure['lat'] ?? null;

        return ($value === null || $value === '') ? null : (float) $value;
    }

    private static function lngOrNull(array $treasure): ?float
    {
        $value = $treasure['lng'] ?? null;

        return ($value === null || $value === '') ? null : (float) $value;
    }

    /**
     * Indica se o tesouro tem coordenada definida.
     */
    private static function hasLocation(array $treasure): bool
    {
        return self::latOrNull($treasure) !== null && self::lngOrNull($treasure) !== null;
    }

    /**
     * Resposta 401 padrão.
     */
    private function unauthorized(Response $response): Response
    {
        return $this->json($response, [
            'success' => false,
            'error'   => 'Não autenticado.',
        ], 401);
    }

    /**
     * Monta a resposta JSON com o status informado.
     *
     * @param array<string, mixed> $data
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}