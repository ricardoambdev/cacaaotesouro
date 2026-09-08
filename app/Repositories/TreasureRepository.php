<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Acesso aos dados da tabela `treasures`.
 *
 * Todos os métodos usam prepared statements (PDO), mesmo padrão do
 * UserRepository.
 */
final class TreasureRepository
{
    /**
     * Colunas que podem ser gravadas via create()/update().
     */
    private const COLUMNS = [
        'code',
        'name',
        'description',
        'clue',
        'riddle1',
        'answer1',
        'riddle2',
        'answer2',
        'qr_content',
        'qr_svg_path',
        'lat',
        'lng',
        'sort_order',
        'active',
    ];

    /**
     * Lista todos os tesouros na ordem de jogo (sort_order, depois id).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::get()
            ->query('SELECT * FROM treasures ORDER BY sort_order ASC, id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um tesouro pelo id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM treasures WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Busca um tesouro pelo código único (T01, T02, ...).
     *
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM treasures WHERE code = :code');
        $stmt->execute([':code' => trim($code)]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Tesouros ativos na ordem de jogo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activeOrdered(): array
    {
        return Database::get()
            ->query('SELECT * FROM treasures WHERE active = 1 ORDER BY sort_order ASC, id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Próximo sort_order disponível (MAX + 1).
     */
    public static function nextSortOrder(): int
    {
        $value = (int) Database::get()
            ->query('SELECT COALESCE(MAX(sort_order), 0) FROM treasures')
            ->fetchColumn();

        return $value + 1;
    }

    /**
     * Cria um tesouro e retorna o id gerado.
     *
     * @param array<string, mixed> $data Campos do tesouro (ver COLUMNS)
     */
    public static function create(array $data): int
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare(
            'INSERT INTO treasures '
            . '(code, name, description, clue, riddle1, answer1, riddle2, answer2, '
            . 'qr_content, qr_svg_path, lat, lng, sort_order, active, created_at) '
            . 'VALUES '
            . '(:code, :name, :description, :clue, :riddle1, :answer1, :riddle2, :answer2, '
            . ':qr_content, :qr_svg_path, :lat, :lng, :sort_order, :active, :created_at)'
        );

        $stmt->execute([
            ':code'          => (string) ($data['code'] ?? ''),
            ':name'          => (string) ($data['name'] ?? ''),
            ':description'   => (string) ($data['description'] ?? ''),
            ':clue'          => (string) ($data['clue'] ?? ''),
            ':riddle1'       => (string) ($data['riddle1'] ?? ''),
            ':answer1'       => (string) ($data['answer1'] ?? ''),
            ':riddle2'       => (string) ($data['riddle2'] ?? ''),
            ':answer2'       => (string) ($data['answer2'] ?? ''),
            ':qr_content'    => (string) ($data['qr_content'] ?? ''),
            ':qr_svg_path'   => (string) ($data['qr_svg_path'] ?? ''),
            ':lat'           => self::nullIfEmpty($data['lat'] ?? null),
            ':lng'           => self::nullIfEmpty($data['lng'] ?? null),
            ':sort_order'    => (int) ($data['sort_order'] ?? 0),
            ':active'        => (int) ($data['active'] ?? 0),
            ':created_at'    => (string) ($data['created_at'] ?? date('Y-m-d H:i:s')),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Atualiza um tesouro. Apenas colunas conhecidas são gravadas e
     * `updated_at` é sempre renovado.
     *
     * @param int                  $id   Id do tesouro
     * @param array<string, mixed> $data Campos a atualizar (ver COLUMNS)
     */
    public static function update(int $id, array $data): void
    {
        $sets = [];
        $params = [':id' => $id];

        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = "$column = :$column";

                if ($column === 'lat' || $column === 'lng') {
                    $params[":$column"] = self::nullIfEmpty($data[$column] ?? null);
                } elseif ($column === 'sort_order' || $column === 'active') {
                    $params[":$column"] = (int) $data[$column];
                } else {
                    $params[":$column"] = (string) $data[$column];
                }
            }
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = :updated_at';
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $stmt = Database::get()->prepare(
            'UPDATE treasures SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );

        $stmt->execute($params);
    }

    /**
     * Exclui um tesouro pelo id.
     */
    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare('DELETE FROM treasures WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Reordena os tesouros conforme a posição na lista informada.
     *
     * @param array<int, int|string> $orderedIds Ids na ordem desejada
     */
    public static function reorder(array $orderedIds): void
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare(
            'UPDATE treasures SET sort_order = :sort_order, updated_at = :updated_at '
            . 'WHERE id = :id'
        );

        $pdo->beginTransaction();

        try {
            foreach (array_values($orderedIds) as $index => $id) {
                $stmt->execute([
                    ':sort_order' => $index + 1,
                    ':updated_at' => date('Y-m-d H:i:s'),
                    ':id'         => (int) $id,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Converte '' / null em null (colunas lat/lng).
     *
     * @param mixed $value
     */
    private static function nullIfEmpty($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}