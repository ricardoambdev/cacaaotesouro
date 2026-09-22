<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateTimeImmutable;
use PDO;

/**
 * Controle de tentativas do COFRE virtual da gincana.
 *
 * A página do cofre é pública (sem login), então o controle de tentativas
 * é feito por IP em `vault_attempts`:
 *  - erros seguidos atingindo `vaultMaxAttempts` bloqueiam o cofre;
 *  - o tempo de bloqueio é `vaultBlockMinutes`;
 *  - com `vaultBlockNextDay` ligado, a partir de 3 bloqueios o cofre fica
 *    bloqueado até o dia seguinte.
 */
final class VaultRepository
{
    /**
     * IP do visitante — usado como chave do bloqueio.
     */
    public static function clientIp(): string
    {
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if ($first !== '') {
                return mb_substr($first, 0, 45);
            }
        }

        return mb_substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
    }

    /**
     * Situação atual do IP no cofre.
     *
     * @return array{wrong_streak: int, blocks: int, blocked_until: ?string}
     */
    public static function state(string $ip): array
    {
        $stmt = Database::get()->prepare(
            'SELECT wrong_streak, blocks, blocked_until FROM vault_attempts WHERE ip = :ip'
        );
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return ['wrong_streak' => 0, 'blocks' => 0, 'blocked_until' => null];
        }

        return [
            'wrong_streak'  => (int) ($row['wrong_streak'] ?? 0),
            'blocks'        => (int) ($row['blocks'] ?? 0),
            'blocked_until' => $row['blocked_until'] !== null ? (string) $row['blocked_until'] : null,
        ];
    }

    /**
     * O cofre está bloqueado para este IP? (Considera bloqueio expirado.)
     */
    public static function blockedUntil(string $ip): ?string
    {
        $state = self::state($ip);
        $until = $state['blocked_until'];

        if ($until === null || $until === '') {
            return null;
        }

        if (strtotime($until) <= time()) {
            // Bloqueio venceu: zera o streak e libera.
            self::save($ip, 0, $state['blocks'], null);

            return null;
        }

        return $until;
    }

    /**
     * Registra um erro e (se necessário) bloqueia o cofre.
     *
     * @return array{blocked: bool, blocked_until: ?string, wrong_streak: int, attempts_left: int}
     */
    public static function registerWrong(string $ip): array
    {
        $maxAttempts = max(1, (int) SettingsRepository::get('vaultMaxAttempts', '3'));
        $blockMinutes = max(1, (int) SettingsRepository::get('vaultBlockMinutes', '5'));
        $nextDay = (string) SettingsRepository::get('vaultBlockNextDay', '0') === '1';

        $state = self::state($ip);
        $streak = $state['wrong_streak'] + 1;
        $blocks = $state['blocks'];
        $blockedUntil = null;

        if ($streak >= $maxAttempts) {
            $streak = 0;
            $blocks++;

            // A partir do 3º bloqueio: até o dia seguinte (se a opção estiver ligada).
            if ($nextDay && $blocks >= 3) {
                $blockedUntil = (new DateTimeImmutable('tomorrow 00:00:00'))->format('Y-m-d H:i:s');
            } else {
                $blockedUntil = date('Y-m-d H:i:s', time() + ($blockMinutes * 60));
            }
        }

        self::save($ip, $streak, $blocks, $blockedUntil);

        return [
            'blocked'       => $blockedUntil !== null,
            'blocked_until' => $blockedUntil,
            'wrong_streak'  => $streak,
            'attempts_left' => max(0, $maxAttempts - $streak),
        ];
    }

    /**
     * Acertou o código: zera os erros seguidos (mantém o histórico de
     * bloqueios, que conta para a regra do "até o dia seguinte").
     */
    public static function registerCorrect(string $ip): void
    {
        $state = self::state($ip);

        self::save($ip, 0, $state['blocks'], null);
    }

    /**
     * Coordenada configurada para o cofre (onde ele está no mundo físico).
     *
     * @return array{lat: float, lng: float, radius: int}|null
     */
    public static function coordinate(): ?array
    {
        $lat = trim((string) SettingsRepository::get('vaultLat', ''));
        $lng = trim((string) SettingsRepository::get('vaultLng', ''));
        $radius = (int) SettingsRepository::get('vaultRadius', '100');

        if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        return [
            'lat'    => (float) $lat,
            'lng'    => (float) $lng,
            'radius' => $radius > 0 ? $radius : 100,
        ];
    }

    /**
     * O visitante está dentro do raio permitido do cofre?
     *
     * Sem coordenada configurada o cofre NÃO é bloqueado por localização
     * (o organizador pode testar); com coordenada, exige estar no raio.
     *
     * @return array{ok: bool, reason: string, distance: ?float, radius: int}
     */
    public static function inRange(?float $lat, ?float $lng): array
    {
        $coordinate = self::coordinate();

        if ($coordinate === null) {
            return ['ok' => true, 'reason' => 'sem_coordenada', 'distance' => null, 'radius' => 0];
        }

        if ($lat === null || $lng === null) {
            return [
                'ok'       => false,
                'reason'   => 'sem_localizacao',
                'distance' => null,
                'radius'   => $coordinate['radius'],
            ];
        }

        $distance = haversine_meters($lat, $lng, $coordinate['lat'], $coordinate['lng']);

        return [
            'ok'       => $distance <= $coordinate['radius'],
            'reason'   => $distance <= $coordinate['radius'] ? 'no_local' : 'fora_do_raio',
            'distance' => round($distance, 1),
            'radius'   => $coordinate['radius'],
        ];
    }

    /**
     * Grava a coordenada do cofre (capturada pelo app do admin).
     */
    public static function setCoordinate(float $lat, float $lng, ?int $radius = null): void
    {
        $data = [
            'vaultLat' => (string) $lat,
            'vaultLng' => (string) $lng,
        ];

        if ($radius !== null && $radius > 0) {
            $data['vaultRadius'] = (string) $radius;
        }

        SettingsRepository::update($data);
    }

    /**
     * Lista dos IPs bloqueados no momento (para o painel do cofre).
     *
     * @return array<int, array{ip: string, blocked_until: string, blocks: int}>
     */
    public static function blockedList(): array
    {
        $stmt = Database::get()->prepare(
            'SELECT ip, blocked_until, blocks FROM vault_attempts '
            . 'WHERE blocked_until IS NOT NULL AND blocked_until > :now '
            . 'ORDER BY blocked_until ASC'
        );
        $stmt->execute([':now' => date('Y-m-d H:i:s')]);

        return array_map(static function (array $row): array {
            return [
                'ip'            => (string) $row['ip'],
                'blocked_until' => (string) $row['blocked_until'],
                'blocks'        => (int) $row['blocks'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Limpa o controle de tentativas (usado ao reconfigurar/limpar o jogo).
     */
    public static function clearAll(): void
    {
        Database::get()->exec('DELETE FROM vault_attempts');
    }

    private static function save(string $ip, int $wrongStreak, int $blocks, ?string $blockedUntil): void
    {
        $pdo = Database::get();

        $driver = app_config('db.driver', 'sqlite');
        $now = date('Y-m-d H:i:s');

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare(
                'INSERT INTO vault_attempts (ip, wrong_streak, blocks, blocked_until, updated_at) '
                . 'VALUES (:ip, :wrong_streak, :blocks, :blocked_until, :updated_at) '
                . 'ON DUPLICATE KEY UPDATE wrong_streak = :wrong_streak_u, '
                . 'blocks = :blocks_u, blocked_until = :blocked_until_u, updated_at = :updated_at_u'
            );

            $stmt->execute([
                ':ip'                => $ip,
                ':wrong_streak'      => $wrongStreak,
                ':blocks'            => $blocks,
                ':blocked_until'     => $blockedUntil,
                ':updated_at'        => $now,
                ':wrong_streak_u'    => $wrongStreak,
                ':blocks_u'          => $blocks,
                ':blocked_until_u'   => $blockedUntil,
                ':updated_at_u'      => $now,
            ]);

            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO vault_attempts (ip, wrong_streak, blocks, blocked_until, updated_at) '
            . 'VALUES (:ip, :wrong_streak, :blocks, :blocked_until, :updated_at) '
            . 'ON CONFLICT(ip) DO UPDATE SET wrong_streak = :wrong_streak_u, '
            . 'blocks = :blocks_u, blocked_until = :blocked_until_u, updated_at = :updated_at_u'
        );

        $stmt->execute([
            ':ip'                => $ip,
            ':wrong_streak'      => $wrongStreak,
            ':blocks'            => $blocks,
            ':blocked_until'     => $blockedUntil,
            ':updated_at'        => $now,
            ':wrong_streak_u'    => $wrongStreak,
            ':blocks_u'          => $blocks,
            ':blocked_until_u'   => $blockedUntil,
            ':updated_at_u'      => $now,
        ]);
    }
}
