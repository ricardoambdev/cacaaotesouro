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
     * - 'aleatorio': UMA permutação aleatória COMPARTILHADA por todas as
     *   equipes (as duas equipes seguem a MESMA sequência — assim ambas
     *   podem encontrar os mesmos tesouros; o que muda é a charada
     *   assinalada a cada equipe).
     *
     * A sequência aleatória é persistida na setting `treasureOrderSequence`
     * (JSON) e regenerada automaticamente quando o conjunto de tesouros
     * ativos muda.
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

        // ── Aleatório: sequência ÚNICA e compartilhada ──────
        $decoded = json_decode((string) SettingsRepository::get('treasureOrderSequence', ''), true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded = array_map('intval', $decoded);
        sort($decoded);
        $current = $activeIds;
        sort($current);

        // Regenera quando vazio ou quando os tesouros ativos mudaram.
        if ($decoded === [] || $decoded !== $current) {
            $ids = $activeIds;
            shuffle($ids);

            SettingsRepository::set('treasureOrderSequence', json_encode($ids));

            return self::$orderCache[$teamId] = $ids;
        }

        // Mantém a ordem salva, mas apenas com os tesouros ainda ativos.
        return self::$orderCache[$teamId] = array_values(array_filter(
            array_map('intval', json_decode((string) SettingsRepository::get('treasureOrderSequence', ''), true) ?: []),
            static fn (int $id): bool => in_array($id, $activeIds, true)
        ));
    }

    /**
     * Indica se a equipe já RESOLVEU um tesouro (deve ser pulado na ordem):
     * - encontrou (acertou a charada) e não foi desclassificado, OU
     * - o tesouro foi desclassificado (não pode ser refeito).
     *
     * @param array<string, mixed> $team Linha da tabela teams
     */
    public static function isTreasurePassed(int $teamId, int $treasureId): bool
    {
        $progress = self::progress($teamId, $treasureId);

        if ($progress === null) {
            return false;
        }

        // Só o ACERTO da charada conta como resolvido.
        //
        // A desclassificação NÃO encerra o tesouro: a equipe precisa refazê-lo
        // (a marca `disqualified` continua gravada apenas para impedir que ela
        // ganhe os +10 de "primeira a encontrar" neste tesouro).
        return (int) ($progress['riddle_correct'] ?? 0) === 1;
    }

    /**
     * Id do tesouro atual da equipe: o PRIMEIRO tesouro da ordem de jogo
     * que a equipe ainda NÃO resolveu.
     *
     * Derivar do progresso real (em vez de confiar em current_step) torna
     * a progressão auto-corretiva: se a ordem dos tesouros mudar ou um
     * progresso for removido (desclassificação/zerar coordenada), a equipe
     * volta para o primeiro tesouro realmente pendente.
     *
     * @param array<string, mixed> $team Linha da tabela teams
     */
    public static function currentTreasureId(array $team): ?int
    {
        $order = self::treasureOrderForTeam($team);
        $teamId = (int) $team['id'];

        foreach ($order as $treasureId) {
            if (!self::isTreasurePassed($teamId, $treasureId)) {
                return $treasureId;
            }
        }

        return null;
    }

    /**
     * Indica se o desafio final está disponível para a equipe (todos os
     * tesouros da ordem foram completados).
     *
     * @param array<string, mixed> $team Linha da tabela teams
     */
    /**
     * Alguma equipe já encontrou (acertou a charada de) algum tesouro?
     *
     * Usado para liberar o bloqueio do Desafio Final: a organização só pode
     * habilitar/bloquear o desafio depois que a caça começou de verdade.
     */
    public static function anyTreasureFound(): bool
    {
        $count = Database::get()->query(
            'SELECT COUNT(*) FROM team_treasure_progress '
            . 'WHERE riddle_correct = 1 AND disqualified = 0'
        )->fetchColumn();

        return (int) $count > 0;
    }

    /**
     * O Desafio Final está BLOQUEADO pela organização?
     */
    public static function finalBlocked(): bool
    {
        return (string) \App\Repositories\SettingsRepository::get('finalBlocked', '0') === '1';
    }

    public static function finalAvailable(array $team): bool
    {
        $order = self::treasureOrderForTeam($team);

        if ($order === []) {
            return false;
        }

        return self::currentTreasureId($team) === null;
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
     * "Assinatura" do progresso da equipe, para sincronizar VÁRIOS
     * aparelhos da mesma equipe em tempo real.
     *
     * Muda quando algum tesouro é concluído (ou desclassificado) — inclui o
     * horário do último achado, então dois aparelhos nunca ficam com a mesma
     * assinatura depois de um avanço.
     *
     * @return array{signature: string, found: int, last_found: ?string, disqualified: int}
     */
    public static function progressSignature(int $teamId): array
    {
        $stmt = Database::get()->prepare(
            'SELECT '
            . 'SUM(CASE WHEN riddle_correct = 1 THEN 1 ELSE 0 END) AS found, '
            . 'SUM(CASE WHEN disqualified = 1 THEN 1 ELSE 0 END) AS disqualified, '
            . 'MAX(found_at) AS last_found '
            . 'FROM team_treasure_progress WHERE team_id = :team_id'
        );
        $stmt->execute([':team_id' => $teamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $found = (int) ($row['found'] ?? 0);
        $disqualified = (int) ($row['disqualified'] ?? 0);
        $lastFound = $row['last_found'] !== null ? (string) $row['last_found'] : null;

        return [
            'signature'    => md5($teamId . '|' . $found . '|' . $disqualified . '|' . (string) $lastFound),
            'found'        => $found,
            'last_found'   => $lastFound,
            'disqualified' => $disqualified,
        ];
    }

    /**
     * A equipe é a PRIMEIRA a acertar a charada deste tesouro?
     *
     * Verdadeiro quando nenhuma OUTRA equipe já resolveu o tesouro
     * (`riddle_correct = 1`). Usado para o bônus de +10 pontos.
     * Deve ser chamado ANTES de gravar o acerto da própria equipe.
     */
    public static function isFirstFinder(int $treasureId, int $teamId): bool
    {
        $stmt = Database::get()->prepare(
            'SELECT COUNT(*) FROM team_treasure_progress '
            . 'WHERE treasure_id = :treasure_id AND team_id <> :team_id '
            . 'AND riddle_correct = 1 AND disqualified = 0'
        );
        $stmt->execute([':treasure_id' => $treasureId, ':team_id' => $teamId]);

        return (int) $stmt->fetchColumn() === 0;
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