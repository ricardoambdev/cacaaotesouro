<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\Database;
use App\View;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Páginas do jogo no painel admin (requer autenticação):
 * história, desafio final e estado geral da partida.
 */
final class GameController
{
    // ------------------------------------------------------------------
    // História (GET/POST /historia)
    // ------------------------------------------------------------------

    public function history(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string} $user */
        $user = $_SESSION['user'];

        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array) $request->getParsedBody();
            $content = (string) ($body['historyContent'] ?? '');

            if (strlen($content) > 100000) {
                flash_set('error', 'A história é grande demais (máximo 100.000 caracteres).');
                $_SESSION['old'] = ['historyContent' => $content];
                redirect('/historia');
            }

            SettingsRepository::set('historyContent', $content);
            flash_set('success', 'História salva com sucesso.');
            redirect('/historia');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $content = View::render('historia', [
            'historyContent' => (string) ($old['historyContent'] ?? SettingsRepository::get('historyContent', '')),
        ]);

        $response->getBody()->write($this->renderLayout($content, $user, 'historia'));

        return $response;
    }

    // ------------------------------------------------------------------
    // Desafio final (GET/POST /desafio-final)
    // ------------------------------------------------------------------

    public function finalChallenge(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string} $user */
        $user = $_SESSION['user'];

        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array) $request->getParsedBody();

            $finalClue = trim((string) ($body['finalClue'] ?? ''));
            $finalAnswer = trim((string) ($body['finalAnswer'] ?? ''));
            $finalCorrectPoints = trim((string) ($body['finalCorrectPoints'] ?? ''));
            $finalWrongPenalty = trim((string) ($body['finalWrongPenalty'] ?? ''));

            $errors = [];

            if ($finalAnswer === '') {
                $errors[] = 'A resposta do desafio final é obrigatória.';
            } elseif (strlen($finalAnswer) > 64) {
                $errors[] = 'A resposta do desafio final deve ter no máximo 64 caracteres.';
            }

            if ($finalCorrectPoints === '' || !ctype_digit($finalCorrectPoints)
                || (int) $finalCorrectPoints < 1 || (int) $finalCorrectPoints > 1000) {
                $errors[] = 'Os pontos ao acertar o desafio final devem ser um inteiro entre 1 e 1000.';
            }

            if ($finalWrongPenalty === '' || !ctype_digit($finalWrongPenalty)
                || (int) $finalWrongPenalty < 0 || (int) $finalWrongPenalty > 1000) {
                $errors[] = 'Os pontos perdidos por erro devem ser um inteiro entre 0 e 1000.';
            }

            if ($errors !== []) {
                flash_set('error', implode(' ', $errors));
                $_SESSION['old'] = [
                    'finalClue'          => $finalClue,
                    'finalAnswer'        => $finalAnswer,
                    'finalCorrectPoints' => $finalCorrectPoints,
                    'finalWrongPenalty'  => $finalWrongPenalty,
                ];
                redirect('/desafio-final');
            }

            SettingsRepository::update([
                'finalClue'          => $finalClue,
                'finalAnswer'        => $finalAnswer,
                'finalCorrectPoints' => (string) (int) $finalCorrectPoints,
                'finalWrongPenalty'  => (string) (int) $finalWrongPenalty,
            ]);

            flash_set('success', 'Desafio final salvo com sucesso.');
            redirect('/desafio-final');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $content = View::render('desafio_final', [
            'finalClue'          => (string) ($old['finalClue'] ?? SettingsRepository::get('finalClue', '')),
            'finalAnswer'        => (string) ($old['finalAnswer'] ?? SettingsRepository::get('finalAnswer', '')),
            'finalCorrectPoints' => (string) ($old['finalCorrectPoints'] ?? SettingsRepository::get('finalCorrectPoints', '100')),
            'finalWrongPenalty'  => (string) ($old['finalWrongPenalty'] ?? SettingsRepository::get('finalWrongPenalty', '20')),
        ]);

        $response->getBody()->write($this->renderLayout($content, $user, 'desafio-final'));

        return $response;
    }

    // ------------------------------------------------------------------
    // Estado geral do jogo (GET /jogo)
    // ------------------------------------------------------------------

    public function status(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string} $user */
        $user = $_SESSION['user'];

        $teams = array_map(static function (array $team): array {
            return [
                'id'          => (int) $team['id'],
                'name'        => (string) $team['name'],
                'color'       => (string) $team['color'],
                'points'      => (int) $team['points'],
                'status'      => (string) ($team['status'] ?? 'playing'),
                'finished_at' => $team['finished_at'] ?? null,
                'current_step'=> (int) ($team['current_step'] ?? 0),
                'found_count' => GameRepository::foundCount((int) $team['id']),
            ];
        }, TeamRepository::all());

        usort($teams, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        $game = [
            'gameActive'   => (string) SettingsRepository::get('gameActive', '0'),
            'winnerTeamId' => (string) SettingsRepository::get('winnerTeamId', ''),
            'treasureOrder'=> (string) SettingsRepository::get('treasureOrder', 'estabelecida'),
        ];

        if ($game['winnerTeamId'] !== '') {
            $winnerTeam = TeamRepository::find((int) $game['winnerTeamId']);

            $game['winner'] = $winnerTeam !== null
                ? (string) $winnerTeam['name']
                : (string) $game['winnerTeamId'];
        } else {
            $game['winner'] = '';
        }

        $content = View::render('jogo', [
            'teams' => $teams,
            'game'  => $game,
        ]);

        $response->getBody()->write($this->renderLayout($content, $user, 'jogo'));

        return $response;
    }

    // ------------------------------------------------------------------
    // Telão (GET /telao — PÚBLICO, sem autenticação)
    // ------------------------------------------------------------------

    /**
     * Página autônoma do telão (projetada para um monitor grande), sem
     * sidebar nem login. Consome os dados de GET /api/telao (público) e
     * é renderizada fora do layout autenticado.
     */
    public function telao(Request $request, Response $response): Response
    {
        $html = View::render('telao', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    // ------------------------------------------------------------------
    // LIMPEZA / RESET DO JOGO
    // ------------------------------------------------------------------

    /**
     * POST /limpar — apaga todo o progresso do jogo E restaura as
     * configurações padrão (deixa o sistema completamente vazio).
     */
    public function resetGame(Request $request, Response $response): Response
    {
        self::clearGameData();

        // ── Jogo (estado inicial completo) ───────────────────
        SettingsRepository::update([
            'gameStatus'    => 'playing',
            'gameActive'    => '1',
            'winnerTeamId'  => '',
            'gameStartDate' => '',
            'gameStartTime' => '08:00',
            'gameEndTime'   => '17:00',
        ]);

        flash_set('success', 'Sistema limpo! Tudo foi apagado e está pronto para uma nova caçada ao tesouro.');
        redirect('/configuracoes');
    }

    /**
     * POST /limpar-jogo — reseta SOMENTE o jogo das equipes (pontos,
     * progresso, selfies, tesouros, registros), MANTENDO as configurações
     * do admin (data/horários, desafio final, credenciais, ordem).
     */
    public function resetGameProgress(Request $request, Response $response): Response
    {
        self::clearGameData();

        // Só os flags de jogo voltam ao "pronto para começar";
        // as demais configurações são mantidas.
        SettingsRepository::update([
            'gameStatus'   => 'playing',
            'gameActive'   => '1',
            'winnerTeamId' => '',
        ]);

        flash_set('success', 'Jogo resetado! Equipes com 100 pontos, selfies e tesouros apagados. Configurações mantidas.');
        redirect('/configuracoes');
    }

    /**
     * Apaga todos os dados de jogo (progresso, selfies, tesouros, pontos,
     * localizações, mensagens) e reseta as equipes ao estado inicial.
     */
    private static function clearGameData(): void
    {
        $pdo = Database::get();
        $root = dirname(__DIR__);

        // ── Selfies (arquivos) ──────────────────────────────
        $selfiesDir = $root . '/public/uploads/selfies';

        if (is_dir($selfiesDir)) {
            foreach (glob($selfiesDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // ── QR codes dos tesouros (arquivos) ────────────────
        foreach ($pdo->query('SELECT qr_svg_path FROM treasures') as $row) {
            $path = (string) ($row['qr_svg_path'] ?? '');

            if ($path !== '') {
                $file = $root . '/public' . $path;
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // ── Registros ────────────────────────────────────────
        $pdo->exec('DELETE FROM team_treasure_progress');
        $pdo->exec('DELETE FROM points_log');
        $pdo->exec('DELETE FROM team_locations');
        $pdo->exec('DELETE FROM team_messages');
        $pdo->exec('DELETE FROM treasures');

        // ── Equipes (estado inicial) ─────────────────────────
        $pdo->exec(
            "UPDATE teams SET points = 100, status = 'playing', "
            . "finished_at = NULL, current_step = 0, "
            . "session_token = NULL, order_sequence = NULL"
        );
    }

    /**
     * POST /limpar/tesouros — cria 5 tesouros de demonstração
     * (úteis após a limpeza para o sistema não ficar vazio).
     * Códigos já existentes não são duplicados.
     */
    public function createDemoTreasures(Request $request, Response $response): Response
    {
        $pdo = Database::get();

        $demos = [
            ['code' => 'T01', 'name' => 'Praça do Quico', 'description' => 'A praça onde o Quico brinca de bola com a Chiquinha.', 'clue' => 'Procure o banco onde o Quico senta para esperar a Chiquinha.', 'riddle1' => 'Quantas pernas tem o total de personagens da vila?', 'answer1' => '0412', 'riddle2' => 'Qual o número da casa da bruxa do 71?', 'answer2' => '0071'],
            ['code' => 'T02', 'name' => 'Barril da Vila', 'description' => 'Um barril antigo na entrada da vila.', 'clue' => 'Atrás do barril com o buraco redondo.', 'riddle1' => 'Quantas rodas tem o carro do Sr. Madruga?', 'answer1' => '0004', 'riddle2' => 'Quantas letras tem CHAVES?', 'answer2' => '0006'],
            ['code' => 'T03', 'name' => 'Portão do Colégio', 'description' => 'O portão da escola onde todos estudam.', 'clue' => 'No portão, do lado de fora, há um número pintado.', 'riddle1' => 'Qual o ano em que a série começou?', 'answer1' => '1972', 'riddle2' => 'Quantos dedos tem a mão do Professor Girafales?', 'answer2' => '0005'],
            ['code' => 'T04', 'name' => 'Pipoca da Chiquinha', 'description' => 'A barraquinha de pipoca da Chiquinha.', 'clue' => 'Atrás da barraquinha há uma sacola de pipoca amarela.', 'riddle1' => 'Quantos grãos de milho tem uma espiga média?', 'answer1' => '0800', 'riddle2' => 'Quantos minutos tem uma hora?', 'answer2' => '0060'],
            ['code' => 'T05', 'name' => 'Muralha do Pátio', 'description' => 'O muro onde as crianças se escondem.', 'clue' => 'Entre o muro e a árvore grande.', 'riddle1' => 'Quantos dias tem uma semana?', 'answer1' => '0007', 'riddle2' => 'Quantos lados tem um quadrado?', 'answer2' => '0004'],
        ];

        // Códigos já existentes (para não duplicar).
        $existing = array_map('strval', $pdo->query('SELECT code FROM treasures')->fetchAll(PDO::FETCH_COLUMN));

        $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM treasures')->fetchColumn();
        $insert = $pdo->prepare(
            'INSERT INTO treasures '
            . '(code, name, description, clue, riddle1, answer1, riddle2, answer2, '
            . 'qr_content, qr_svg_path, sort_order, active, created_at) '
            . 'VALUES (:code, :name, :description, :clue, :riddle1, :answer1, '
            . ':riddle2, :answer2, :qr_content, :qr_svg_path, :sort_order, 0, :created_at)'
        );
        $updateQr = $pdo->prepare('UPDATE treasures SET qr_content = :qr, qr_svg_path = :svg WHERE id = :id');

        $created = 0;

        foreach ($demos as $t) {
            if (in_array($t['code'], $existing, true)) {
                continue;
            }

            $code = random_alnum(20);
            $nextSort++;

            $insert->execute([
                ':code' => $t['code'], ':name' => $t['name'], ':description' => $t['description'],
                ':clue' => $t['clue'], ':riddle1' => $t['riddle1'], ':answer1' => $t['answer1'],
                ':riddle2' => $t['riddle2'], ':answer2' => $t['answer2'],
                ':qr_content' => $code, ':qr_svg_path' => '', ':sort_order' => $nextSort,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            $id = (int) $pdo->lastInsertId();
            $svg = '';

            try {
                $svg = \App\Services\QrService::generateSvg($code, $id);
            } catch (\Throwable $e) {
                error_log('createDemoTreasures QR: ' . $e->getMessage());
            }

            $updateQr->execute([':qr' => $code, ':svg' => $svg, ':id' => $id]);
            $created++;
        }

        if ($created > 0) {
            flash_set('success', sprintf('%d tesouro(s) de demonstração criado(s). Confirme as coordenadas pelo app admin no local.', $created));
        } else {
            flash_set('error', 'Os 5 tesouros de demonstração já existem.');
        }

        redirect('/configuracoes');
    }

    /**
     * POST /admin/mensagem — envia uma mensagem para 'todos' ou para uma
     * equipe específica (pela cor: 'laranja'|'preta').
     * Dispara notificação no app da equipe.
     */
    public function broadcastMessage(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $message = trim((string) ($body['message'] ?? ''));
        $target = strtolower(trim((string) ($body['target'] ?? 'todos')));

        if ($message === '' || mb_strlen($message) > 500) {
            flash_set('error', 'A mensagem deve ter de 1 a 500 caracteres.');
            redirect('/');
        }

        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'INSERT INTO team_messages (team_id, message, read_at, created_at) '
            . 'VALUES (:team_id, :message, NULL, :created_at)'
        );

        $teams = $target === 'todos'
            ? TeamRepository::all()
            : (($team = TeamRepository::byColor($target)) !== null ? [$team] : []);

        if ($teams === []) {
            flash_set('error', 'Equipe de destino não encontrada.');
            redirect('/');
        }

        foreach ($teams as $team) {
            $stmt->execute([
                ':team_id'    => (int) $team['id'],
                ':message'    => $message,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        flash_set('success', 'Mensagem enviada (notificação disparada no app).');
        redirect('/');
    }

    /**
     * GET /admin/mensagens — histórico recente de mensagens (para o chat).
     */
    public function messageHistory(Request $request, Response $response): Response
    {
        $pdo = Database::get();
        $rows = $pdo->query(
            'SELECT tm.id, tm.team_id, tm.message, tm.created_at, t.name AS team_name '
            . 'FROM team_messages tm '
            . 'LEFT JOIN teams t ON t.id = tm.team_id '
            . 'ORDER BY tm.id DESC LIMIT 30'
        )->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'success' => true,
            'messages' => array_reverse(array_map(static function (array $row): array {
                return [
                    'id'         => (int) $row['id'],
                    'team_id'    => (int) $row['team_id'],
                    'team_name'  => (string) ($row['team_name'] ?? 'Todos'),
                    'message'    => (string) $row['message'],
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, $rows)),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * POST /limpar/historia — reexibe a história para as equipes.
     *
     * Incrementa a "versão" da história: quando o app das equipes percebe
     * a versão diferente da que já exibiu, mostra a história novamente no
     * próximo acesso (mantém o conteúdo atual da história).
     */
    public function resetStorySeen(Request $request, Response $response): Response
    {
        $current = (int) SettingsRepository::get('storyVersion', '0');
        SettingsRepository::set('storyVersion', (string) ($current + 1));

        flash_set('success', 'A história será exibida novamente para as equipes no próximo acesso ao aplicativo.');
        redirect('/configuracoes');
    }

    // ------------------------------------------------------------------
    // Privados
    // ------------------------------------------------------------------

    /**
     * Envolve o conteúdo no layout autenticado.
     *
     * @param array<string, mixed> $user
     */
    private function renderLayout(string $content, array $user, string $active): string
    {
        return View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => $active,
            'flash'    => flash_get(),
            'content'  => $content,
        ]);
    }
}