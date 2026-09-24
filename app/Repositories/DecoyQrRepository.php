<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * QR codes FALSOS ("iscas").
 *
 * São QR codes que a organização esconde no caminho. Eles NÃO identificam
 * tesouro nenhum: quando a equipe lê um deles, o app mostra a mensagem
 * cadastrada aqui e volta para a tela inicial.
 */
final class DecoyQrRepository
{
    /**
     * Todos os QR codes falsos (mais recentes primeiro).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = Database::get()
            ->query('SELECT * FROM decoy_qrs ORDER BY id DESC')
            ->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM decoy_qrs WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Procura pelo CONTEÚDO lido no QR code (é o que o app envia).
     */
    public static function findByContent(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $stmt = Database::get()->prepare('SELECT * FROM decoy_qrs WHERE content = :content LIMIT 1');
        $stmt->execute([':content' => $content]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Cria um QR code falso com um conteúdo aleatório e devolve o id.
     */
    public static function create(string $message): int
    {
        $pdo = Database::get();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO decoy_qrs (content, message, qr_svg_path, created_at) '
            . 'VALUES (:content, :message, \'\', :created_at)'
        );
        $stmt->execute([
            // Código aleatório IGUAL ao dos tesouros (mesmo formato, sem
            // nenhuma pista de que é uma isca): se alguém ler o QR com a
            // câmera do celular, não descobre que é pegadinha.
            ':content'    => random_alnum(20),
            ':message'    => $message,
            ':created_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateQrPath(int $id, string $path): void
    {
        $stmt = Database::get()->prepare(
            'UPDATE decoy_qrs SET qr_svg_path = :path, updated_at = :now WHERE id = :id'
        );
        $stmt->execute([
            ':path' => $path,
            ':now'  => date('Y-m-d H:i:s'),
            ':id'   => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare('DELETE FROM decoy_qrs WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
