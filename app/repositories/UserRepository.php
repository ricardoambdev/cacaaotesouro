<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Acesso aos dados da tabela `users`.
 *
 * Todos os métodos usam prepared statements (PDO).
 */
final class UserRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Busca um usuário pelo nome de usuário (já normalizado em minúsculas).
     *
     * @return array<string, mixed>|null
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Cria um usuário e retorna o id gerado.
     *
     * @param array{name: string, username: string, email?: string|null, password_hash: string} $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, username, email, password_hash, created_at) '
            . 'VALUES (:name, :username, :email, :password_hash, :created_at)'
        );

        $stmt->execute([
            ':name'          => $data['name'],
            ':username'      => $data['username'],
            ':email'         => $data['email'] ?? null,
            ':password_hash' => $data['password_hash'],
            ':created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Grava o token de recuperação de senha e sua expiração.
     */
    public static function setResetToken(int $id, string $token, string $expires): void
    {
        $stmt = Database::get()->prepare(
            'UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id'
        );

        $stmt->execute([
            ':token'   => $token,
            ':expires' => $expires,
            ':id'      => $id,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByResetToken(string $token): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE reset_token = :token');
        $stmt->execute([':token' => $token]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Atualiza a senha do usuário e limpa o token de recuperação.
     */
    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::get()->prepare(
            'UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_expires = NULL '
            . 'WHERE id = :id'
        );

        $stmt->execute([':password_hash' => $passwordHash, ':id' => $id]);
    }
}