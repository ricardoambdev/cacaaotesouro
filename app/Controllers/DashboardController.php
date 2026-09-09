<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Repositories\GameRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\View;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Painel principal (requer autenticação).
 *
 * Exibe o mapa em tempo real (como o telão) e uma sidebar para gerenciar
 * as equipes: ajustar pontos e desclassificar tesouros encontrados.
 */
final class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string, email: string} $user */
        $user = $_SESSION['user'];

        $teams = array_map(static function (array $team): array {
            return [
                'id'          => (int) $team['id'],
                'name'        => (string) $team['name'],
                'color'       => (string) $team['color'],
                'points'      => (int) $team['points'],
                'status'      => (string) ($team['status'] ?? 'playing'),
                'found_count' => GameRepository::foundCount((int) $team['id']),
            ];
        }, TeamRepository::all());

        // Tesouros encontrados por cada equipe (para desclassificar/ver selfie).
        $foundByTeam = [];
        $pdo = Database::get();
        $rows = $pdo->query(
            'SELECT tt.team_id, t.id, t.code, t.name, tt.found_at, tt.selfie_path '
            . 'FROM team_treasure_progress tt '
            . 'JOIN treasures t ON t.id = tt.treasure_id '
            . 'WHERE tt.riddle_correct = 1 AND tt.disqualified = 0 '
            . 'ORDER BY tt.found_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $teamId = (int) $row['team_id'];
            $foundByTeam[$teamId][] = [
                'id'          => (int) $row['id'],
                'code'        => (string) $row['code'],
                'name'        => (string) $row['name'],
                'found_at'    => $row['found_at'] ?? '',
                'selfie_path' => $row['selfie_path'] ?? '',
            ];
        }

        $content = View::render('dashboard', [
            'user'         => $user,
            'config'       => SettingsRepository::all(),
            'teams'        => $teams,
            'foundByTeam'  => $foundByTeam,
        ]);

        $html = View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => 'painel',
            'flash'    => flash_get(),
            'content'  => $content,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    /**
     * POST /admin/pontos — adiciona/remove pontos de uma equipe.
     */
    public function adjustPoints(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $teamId = (int) ($body['team_id'] ?? 0);
        $delta = (int) ($body['delta'] ?? 0);
        $reason = trim((string) ($body['reason'] ?? ''));

        $team = TeamRepository::find($teamId);

        if ($team === null) {
            flash_set('error', 'Equipe não encontrada.');
            redirect('/');
        }

        if ($delta === 0) {
            flash_set('error', 'Informe um valor de pontos diferente de zero.');
            redirect('/');
        }

        $newPoints = max(0, (int) $team['points'] + $delta);
        TeamRepository::updateGameState($teamId, ['points' => $newPoints]);
        GameRepository::logPoints($teamId, $delta, $reason !== '' ? $reason : 'ajuste manual no painel');

        flash_set('success', sprintf(
            '%s: %s%d pontos (novo total: %d).',
            (string) $team['name'],
            $delta > 0 ? '+' : '',
            $delta,
            $newPoints
        ));
        redirect('/');
    }

    /**
     * POST /admin/desclassificar — anula um tesouro encontrado por uma equipe.
     *
     * Remove os pontos ganhos no tesouro (+20) e da selfie (+5 se enviada),
     * apaga a selfie (arquivo + registro) e marca o tesouro como não
     * encontrado por esta equipe.
     */
    public function disqualifyTreasure(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $teamId = (int) ($body['team_id'] ?? 0);
        $treasureId = (int) ($body['treasure_id'] ?? 0);

        $team = TeamRepository::find($teamId);

        if ($team === null) {
            flash_set('error', 'Equipe não encontrada.');
            redirect('/');
        }

        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'SELECT * FROM team_treasure_progress '
            . 'WHERE team_id = :team_id AND treasure_id = :treasure_id AND riddle_correct = 1'
        );
        $stmt->execute([':team_id' => $teamId, ':treasure_id' => $treasureId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($progress === false) {
            flash_set('error', 'Tesouro não encontrado no progresso desta equipe.');
            redirect('/');
        }

        $treasureName = $treasureId;
        $tStmt = $pdo->prepare('SELECT name FROM treasures WHERE id = :id');
        $tStmt->execute([':id' => $treasureId]);
        $tName = $tStmt->fetchColumn();

        if ($tName !== false) {
            $treasureName = (string) $tName;
        }

        // Reverter pontos: -20 do tesouro e -5 da selfie (se enviada).
        $delta = -20;

        if ((int) ($progress['selfie_points'] ?? 0) === 1) {
            $delta -= 5;
        }

        $newPoints = max(0, (int) $team['points'] + $delta);
        TeamRepository::updateGameState($teamId, ['points' => $newPoints]);

        // Marcar como DESCLASSIFICADO (mantém o registro e a selfie para
        // auditoria; o tesouro NÃO pode ser refeito pela equipe).
        $upd = $pdo->prepare(
            'UPDATE team_treasure_progress SET disqualified = 1, updated_at = NOW() '
            . 'WHERE team_id = :team_id AND treasure_id = :treasure_id'
        );
        $upd->execute([':team_id' => $teamId, ':treasure_id' => $treasureId]);

        GameRepository::logPoints($teamId, $delta, 'desclassificação do tesouro "' . $treasureName . '"');

        flash_set('success', sprintf(
            'Tesouro "%s" desclassificado da %s (%d pontos).',
            (string) $treasureName,
            (string) $team['name'],
            $delta
        ));
        redirect('/');
    }
}