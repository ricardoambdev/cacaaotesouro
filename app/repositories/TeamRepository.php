<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Acesso aos dados da tabela `teams` (Equipes Laranja e Preta).
 *
 * Todos os métodos usam prepared statements (PDO).
 */
final class TeamRepository
{
    /**
     * Retorna todas as equipes, indexadas por id.
     *
     * Inclui `password` (senha em texto puro, para o admin visualizar/editar).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = Database::get()
            ->query('SELECT * FROM teams ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);

        $teams = [];

        foreach ($rows as $row) {
            $teams[(int) $row['id']] = $row;
        }

        return $teams;
    }

    /**
     * Busca uma equipe pelo id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM teams WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Busca uma equipe pela cor (ex.: 'laranja', 'preta').
     *
     * O retorno inclui `password` (senha em texto puro, para exibição no admin)
     * e `password_hash` (usado na autenticação da API).
     *
     * @return array<string, mixed>|null
     */
    public static function byColor(string $color): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM teams WHERE color = :color');
        $stmt->execute([':color' => strtolower(trim($color))]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Busca uma equipe pelo nome de usuário (normalizado em minúsculas).
     *
     * @return array<string, mixed>|null
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM teams WHERE username = :username');
        $stmt->execute([':username' => strtolower(trim($username))]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Atualiza o username e, opcionalmente, a senha de uma equipe.
     *
     * A senha é guardada em DUAS colunas sincronizadas:
     * - `password`: texto puro, para o admin visualizar/editar no formulário;
     * - `password_hash`: hash gerado por password_hash(), usado na autenticação
     *   da API (POST /api/team/login).
     *
     * Quando $password/$passwordHash é null ou '', o respectivo valor atual é
     * MANTIDO (ex.: admin deixou o campo de senha em branco). Sempre atualiza
     * updated_at = NOW().
     *
     * @param int         $id           Id da equipe
     * @param string      $username     Novo username (já normalizado)
     * @param string|null $password     Nova senha em texto puro (null = manter)
     * @param string|null $passwordHash Novo hash (null = manter)
     */
    public static function updateCredentials(
        int $id,
        string $username,
        ?string $password,
        ?string $passwordHash
    ): void {
        $sets = ['username = :username'];
        $params = [':username' => $username, ':id' => $id];

        if ($password !== null && $password !== '') {
            $sets[] = 'password = :password';
            $params[':password'] = $password;
        }

        if ($passwordHash !== null && $passwordHash !== '') {
            $sets[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $passwordHash;
        }

        $sets[] = 'updated_at = :updated_at';
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $stmt = Database::get()->prepare(
            'UPDATE teams SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }

    /**
     * Colunas de jogo que podem ser atualizadas via updateGameState().
     */
    private const GAME_STATE_COLUMNS = [
        'points',
        'session_token',
        'status',
        'finished_at',
        'current_step',
        'order_sequence',
    ];

    /**
     * Atualiza apenas os campos de estado de jogo informados em $patch.
     *
     * Chaves permitidas: points, session_token, status, finished_at,
     * current_step, order_sequence. Sempre renova updated_at.
     *
     * @param array<string, mixed> $patch
     */
    public static function updateGameState(int $id, array $patch): void
    {
        $sets = [];
        $params = [':id' => $id];

        foreach (self::GAME_STATE_COLUMNS as $column) {
            if (array_key_exists($column, $patch)) {
                $sets[] = "$column = :$column";
                $value = $patch[$column];

                if ($column === 'points' || $column === 'current_step') {
                    $params[":$column"] = (int) $value;
                } elseif ($column === 'session_token') {
                    $params[":$column"] = ($value === null || $value === '') ? null : (string) $value;
                } elseif ($column === 'finished_at') {
                    $params[":$column"] = ($value === null || $value === '') ? null : (string) $value;
                } else {
                    $params[":$column"] = ($value === null || $value === '') ? null : (string) $value;
                }
            }
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = :updated_at';
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $stmt = Database::get()->prepare(
            'UPDATE teams SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }

    /**
     * Soma um delta (positivo ou negativo) aos pontos da equipe.
     */
    public static function addPoints(int $id, int $delta): void
    {
        $stmt = Database::get()->prepare(
            'UPDATE teams SET points = points + :delta, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':delta'     => $delta,
            ':updated_at'=> date('Y-m-d H:i:s'),
            ':id'        => $id,
        ]);
    }

    /**
     * Define (ou limpa) o token de sessão/device da equipe.
     */
    public static function setSessionToken(int $id, ?string $token): void
    {
        $stmt = Database::get()->prepare(
            'UPDATE teams SET session_token = :token, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':token'      => ($token === null || $token === '') ? null : $token,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id'         => $id,
        ]);
    }

    /**
     * Limpa o token de sessão de TODAS as equipes (desconexão geral).
     */
    public static function clearAllSessions(): void
    {
        Database::get()->exec(
            "UPDATE teams SET session_token = NULL, updated_at = '" . date('Y-m-d H:i:s') . "'"
        );
    }

    /**
     * Marca a equipe como finalizada (status 'finished' + horário).
     */
    public static function markFinished(int $id, string $when): void
    {
        $stmt = Database::get()->prepare(
            "UPDATE teams SET status = 'finished', finished_at = :when, updated_at = :updated_at "
            . 'WHERE id = :id'
        );
        $stmt->execute([
            ':when'      => $when,
            ':updated_at'=> date('Y-m-d H:i:s'),
            ':id'        => $id,
        ]);
    }
}