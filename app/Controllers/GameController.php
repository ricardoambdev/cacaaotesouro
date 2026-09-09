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
     * POST /limpar — apaga todo o progresso do jogo e deixa o sistema
     * vazio, pronto para uma nova caçada.
     *
     * Remove: progresso dos tesouros, selfies (arquivos + registros),
     * log de pontos, localizações, mensagens, tesouros (com seus QR SVG)
     * e reseta as equipes e o estado do jogo.
     */
    public function resetGame(Request $request, Response $response): Response
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

        // ── Jogo (estado inicial) ────────────────────────────
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