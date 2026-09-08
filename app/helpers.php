<?php

declare(strict_types=1);

/**
 * Funções utilitárias globais do sistema Caça ao Tesouro.
 *
 * Este arquivo é carregado pelo front controller (public/index.php) e
 * pelo bootstrap (app/bootstrap.php). Não declarar classes aqui.
 */

use App\Repositories\SettingsRepository;

/**
 * Escapa uma string para saída segura em HTML (XSS).
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Lê um valor da configuração global usando notação de ponto.
 *
 * Exemplos: app_config('app.env'), app_config('db.driver').
 *
 * @return mixed
 */
function app_config(string $key, $default = null)
{
    global $__caca_config;

    if ($__caca_config === null) {
        $__caca_config = require dirname(__DIR__) . '/config.php';
    }

    $value = $__caca_config;

    foreach (explode('.', $key) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $default;
        }
    }

    return $value;
}

/**
 * Invalida o cache da configuração — o próximo app_config() relê o
 * config.php (usado pelo instalador após gravar data/install.php).
 */
function app_config_reload(): void
{
    global $__caca_config;
    $__caca_config = null;
}

/**
 * Redireciona para uma URL (302) e encerra a execução.
 */
function redirect(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Grava uma mensagem "flash" (exibida uma única vez na próxima página).
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Lê e remove a mensagem "flash" pendente.
 *
 * @return array{type: string, message: string}|null
 */
function flash_get(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    return is_array($flash) ? $flash : null;
}

/**
 * Retorna (e cria se necessário) o token CSRF da sessão.
 */
function csrf_token(): string
{
    return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
}

/**
 * Retorna o campo HTML oculto com o token CSRF.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

/**
 * Verifica se o token CSRF enviado via POST corresponde ao da sessão.
 *
 * Aceita o token em $_POST['_token'] (forms tradicionais) OU no header
 * `X-CSRF-Token` (requisições JSON feitas por JavaScript, ex.: reordenação
 * de tesouros com fetch).
 */
function csrf_verify(): bool
{
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!is_string($token) || $token === '') {
        return false;
    }

    return hash_equals(csrf_token(), $token);
}

/**
 * Atalho para ler uma configuração do sistema (tabela settings).
 *
 * @return mixed
 */
function setting(string $key, $default = null)
{
    return SettingsRepository::get($key, $default);
}

/**
 * Gera uma string aleatória alfanumérica (letras A-Z + dígitos 0-9,
 * sem caracteres ambíguos como 0/O/1/I) usando random_bytes.
 *
 * Usada como conteúdo do QR code dos tesouros (20 caracteres).
 */
function random_alnum(int $length = 20): string
{
    if ($length < 1) {
        throw new InvalidArgumentException('random_alnum: o tamanho deve ser maior que zero.');
    }

    // Sem 0, O, 1, I para evitar confusão de leitura.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $alphaLen = strlen($alphabet);

    $result = '';
    $bytes = random_bytes($length);

    for ($i = 0; $i < $length; $i++) {
        $result .= $alphabet[ord($bytes[$i]) % $alphaLen];
    }

    return $result;
}

/**
 * Detecta o IP local da máquina na rede (LAN).
 *
 * Ordem de tentativa:
 * 1. $_SERVER['SERVER_ADDR'] (IP do servidor web) — se não for loopback
 *    (127.0.0.1/::1/0.0.0.0) e não começar com 10.10 (IP do Valet);
 * 2. gethostbyname(gethostname()) — resolução do nome da máquina, se o
 *    resultado não for loopback;
 * 3. exec('ipconfig') — parse da linha "IPv4 ..." (padrão Windows),
 *    procurando o primeiro IP privado que não seja loopback.
 *
 * Retorna sempre string ('' quando não detectar).
 */
function lan_ip(): string
{
    // IP aceitável: válido e não loopback.
    $isUsable = static function (string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)
            && !str_starts_with($ip, '127.');
    };

    // IPv4 em faixa privada (RFC 1918): 10.x, 172.16-31.x, 192.168.x.
    $isPrivateIpv4 = static function (string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $parts = array_map('intval', explode('.', $ip));
        $first = $parts[0];
        $second = $parts[1] ?? 0;

        return $first === 10
            || ($first === 172 && $second >= 16 && $second <= 31)
            || ($first === 192 && $second === 168);
    };

    // 1. IP do servidor web (apache/nginx/valet), quando não for loopback.
    $serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');

    if ($serverAddr !== '' && $isUsable($serverAddr)) {
        return $serverAddr;
    }

    // 2. Resolução do nome da máquina (gethostbyname devolve o próprio nome
    //    quando não resolve — nesse caso é descartado).
    $hostname = trim((string) gethostname());

    if ($hostname !== '') {
        $resolved = gethostbyname($hostname);

        if ($resolved !== '' && $resolved !== $hostname && $isUsable($resolved)) {
            return $resolved;
        }
    }

    // 3. Parse do ipconfig (Windows): primeiro IPv4 privado não loopback.
    $output = [];
    $exitCode = -1;

    @exec('ipconfig', $output, $exitCode);

    if ($exitCode === 0) {
        foreach ($output as $line) {
            if (stripos($line, 'IPv4') === false) {
                continue;
            }

            $pos = strpos($line, ':');

            if ($pos === false) {
                continue;
            }

            $ip = trim(substr($line, $pos + 1));

            if ($isUsable($ip) && $isPrivateIpv4($ip)) {
                return $ip;
            }
        }
    }

    return '';
}

/**
 * Distância (em metros) entre dois pontos geográficos usando a fórmula
 * de Haversine. Raio médio da Terra: 6.371.000 m.
 */
function haversine_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}