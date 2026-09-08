<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Acesso aos dados da tabela `settings` (configurações do sistema).
 */
final class SettingsRepository
{
    /**
     * Retorna todas as configurações como um mapa chave => valor.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $rows = Database::get()
            ->query('SELECT `key`, `value` FROM `settings`')
            ->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }

        return $settings;
    }

    /**
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::all();

        return $settings[$key] ?? $default;
    }

    /**
     * Grava (ou atualiza) uma configuração usando upsert por driver.
     */
    public static function set(string $key, string $value): void
    {
        $pdo = Database::get();
        $driver = app_config('db.driver', 'sqlite');

        $sql = $driver === 'mysql'
            ? 'INSERT INTO `settings` (`key`, `value`) VALUES (:key, :value) '
                . 'ON DUPLICATE KEY UPDATE `value` = :value2'
            : 'INSERT INTO `settings` (`key`, `value`) VALUES (:key, :value) '
                . 'ON CONFLICT(`key`) DO UPDATE SET `value` = :value2';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
    }

    /**
     * Grava várias configurações de uma vez.
     *
     * @param array<string, string|int> $settings
     */
    public static function update(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::set((string) $key, (string) $value);
        }
    }
}