<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\View;
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

            $errors = [];

            if ($finalAnswer === '') {
                $errors[] = 'A resposta do desafio final é obrigatória.';
            } elseif (strlen($finalAnswer) > 64) {
                $errors[] = 'A resposta do desafio final deve ter no máximo 64 caracteres.';
            }

            if ($errors !== []) {
                flash_set('error', implode(' ', $errors));
                $_SESSION['old'] = ['finalClue' => $finalClue, 'finalAnswer' => $finalAnswer];
                redirect('/desafio-final');
            }

            SettingsRepository::update([
                'finalClue'   => $finalClue,
                'finalAnswer' => $finalAnswer,
            ]);

            flash_set('success', 'Desafio final salvo com sucesso.');
            redirect('/desafio-final');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $content = View::render('desafio_final', [
            'finalClue'   => (string) ($old['finalClue'] ?? SettingsRepository::get('finalClue', '')),
            'finalAnswer' => (string) ($old['finalAnswer'] ?? SettingsRepository::get('finalAnswer', '')),
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