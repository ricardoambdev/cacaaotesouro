<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Lógica de estado do jogo "Caça ao Tesouro".
 *
 * Centraliza a ordem dos tesouros por equipe, o progresso (check-in,
 * charada assinalada, selfie) e o histórico de pontos.
 */
final class GameRepository
{
    /**
     * Cache da ordem dos tesouros POR REQUISIÇÃO (chave: team_id).
     *
     * Garante que, dentro da mesma requisição HTTP, todas as chamadas de
     * treasureOrderForTeam() devolvam a MESMA ordem — inclusive quando a
     * permutação aleatória é gerada na primeira chamada (senão cada chamada
     * sortearia uma ordem nova e a última gravada divergiria da exibida).
     *
     * @var array<int, array<int, int>>
     */
    private static array $orderCache = [];

    /**
     * Retorna os ids dos tesouros NA ORDEM de jogo da equipe.
     *
     * - 'estabelecida': ids de activeOrdered() (sort_order).
     * - 'aleatorio': permutação aleatória FIXA por equipe. A primeira vez
     *   que é chamada, gera a permutação e persiste em teams.order_sequence
     *   (JSON). Nas chamadas seguintes, decodifica o valor salvo.
     *
     * @param array<string, mixed> $team Linha da tabela teams
     *
     * @return array<int, int>
     */
    public static function treasureOrderForTeam(array $team): array
    {
        $teamId = (int) $team['id'];

        if (isset(self::$orderCache[$teamId])) {
            return self::$orderCache[$teamId];
        }

        $activeIds = array_map(
            static fn (array $t): int => (int) $t['id'],
            TreasureRepository::activeOrdered()
        );

        $mode = (string) SettingsRepository::get('treasureOrder', 'estabelecida');

        if ($mode !== 'aleatorio') {
            return self::$orderCache[$teamId] = $activeIds;
        }

        $sequence = $team['order_sequence'] ?? null;

        if ($sequence === null || trim((string) $sequence) === '') {
            $ids = $activeIds;
            shuffle($ids);

            TeamRepository::updateGameState($teamId, [
                'order_sequence' => json_encode($ids),
            ]);

            return self::$orderCache[$teamId] = $ids;
        }

        $decoded = json_decode((string) $sequence, true);

        if (!is_array($decoded)) {
            return self::$orderCache[$teamId] = $activeIds;
        }

        return self::$orderCache[$teamId] = array_map('intval', $decoded);
    }

    /**
     * Id do tesouro atual da equipe (ordered[current_step]), ou null se a
     * equipe já completou todos os tesouros ativos.
     *
     * @param array<string, mixed> $team Linha da tabela teams
     */
    public static function currentTreasureId(array $team): ?int
    {
        $order = self::treasureOrderForTeam($team);
        $step = (int) $team['current_step'];

        return $order[$step] ?? null;
    }

    /**
     * Indica se o desafio final está disponível para a equipe
     * (current_step >= nº de tesouros ativos).
     *
     * @param array<string, mixed> $team Linha da tabela teams
     */
    public static function finalAvailable(array $team): bool
    {
        $order = self::treasureOrderForTeam($team);

        return (int) $team['current_step'] >= count($order);
    }

    /**
     * Progresso de uma equipe em um tesouro específico.
     *
     * @return array<string, mixed>|null
     */
    public static function progress(int $teamId, int $treasureId): ?array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM team_treasure_progress '
            . 'WHERE team_id = :team_id AND treasure_id = :treasure_id'
        );
        $stmt->execute([':team_id' => $teamId, ':treasure_id' => $treasureId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Linhas de progresso de TODAS as equipes em um tesouro
     * (usado pelo admin para ver quem já encontrou cada tesouro).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function progressForTreasure(int $treasureId): array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM team_treasure_progress WHERE treasure_id = :treasure_id '
            . 'ORDER BY team_id ASC'
        );
        $stmt->execute([':treasure_id' => $treasureId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sorteia e grava a charada (1 ou 2) assinalada à equipe em um tesouro.
     *
     * Regra: se a OUTRA equipe já tem uma charada assinalada para este
     * tesouro, devolve o oposto (1→2, 2→1); caso contrário sorteia
     * rand(1,2). A linha de progresso é criada se ainda não existir.
     */
    public static function assignRiddle(int $teamId, int $treasureId): int
    {
        $assigned = null;

        foreach (TeamRepository::all() as $otherTeam) {
            if ((int) $otherTeam['id'] === $teamId) {
                continue;
            }

            $row = self::progress((int) $otherTeam['id'], $treasureId);

            if ($row !== null && in_array((int) $row['assigned_riddle'], [1, 2], true)) {
                $assigned = (int) $row['assigned_riddle'] === 1 ? 2 : 1;
                break;
            }
        }

        if ($assigned === null) {
            $assigned = random_int(1, 2);
        }

        $pdo = Database::get();
        $driver = app_config('db.driver', 'sqlite');
        $now = date('Y-m-d H:i:s');

        $sql = $driver === 'mysql'
            ? 'INSERT INTO team_treasure_progress '
                . '(team_id, treasure_id, assigned_riddle, created_at, updated_at) '
                . 'VALUES (:team_id, :treasure_id, :assigned_riddle, :created_at, :updated_at) '
                . 'ON DUPLICATE KEY UPDATE assigned_riddle = :assigned_riddle2, updated_at = :updated_at2'
            : 'INSERT INTO team_treasure_progress '
                . '(team_id, treasure_id, assigned_riddle, created_at, updated_at) '
                . 'VALUES (:team_id, :treasure_id, :assigned_riddle, :created_at, :updated_at) '
                . 'ON CONFLICT(team_id, treasure_id) DO UPDATE SET '
                . 'assigned_riddle = :assigned_riddle2, updated_at = :updated_at2';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':team_id'            => $teamId,
            ':treasure_id'        => $treasureId,
            ':assigned_riddle'    => $assigned,
            ':created_at'         => $now,
            ':updated_at'         => $now,
            ':assigned_riddle2'   => $assigned,
            ':updated_at2'        => $now,
        ]);

        return $assigned;
    }

    /**
     * Registra uma movimentação de pontos no histórico (tabela points_log).
     */
    public static function logPoints(int $teamId, int $delta, string $reason): void
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO points_log (team_id, delta, reason, created_at) '
            . 'VALUES (:team_id, :delta, :reason, :created_at)'
        );
        $stmt->execute([
            ':team_id'   => $teamId,
            ':delta'     => $delta,
            ':reason'    => $reason,
            ':created_at'=> date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Número de tesouros encontrados (charada correta) por uma equipe.
     */
    public static function foundCount(int $teamId): int
    {
        $stmt = Database::get()->prepare(
            'SELECT COUNT(*) FROM team_treasure_progress '
            . 'WHERE team_id = :team_id AND riddle_correct = 1'
        );
        $stmt->execute([':team_id' => $teamId]);

        return (int) $stmt->fetchColumn();
    }
}