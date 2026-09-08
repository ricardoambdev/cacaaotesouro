<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Configurações gerais do sistema (requer autenticação).
 */
final class SettingsController
{
    public function show(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string, email: string} $user */
        $user = $_SESSION['user'];

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        // Variáveis da aba "API & Desenvolvimento".
        $lanIp = lan_ip();
        $appUrl = (string) app_config('app.url', '');
        $isDevEnv = app_config('app.env', 'prod') === 'dev';

        $systemUrl = ($isDevEnv && $lanIp !== '')
            ? 'http://' . $lanIp . ':8080'
            : $appUrl;

        $apiUrl = $systemUrl . '/api';

        $devMode = (string) SettingsRepository::get('apiDevMode', '0') === '1' || $isDevEnv;

        $hasApk = is_file(dirname(__DIR__, 2) . '/public/uploads/apk/cacaaotesouro.apk');

        // Matriz de ambientes: detecta de onde o painel está sendo acessado
        // e monta as URLs da API a usar no aplicativo (servidor/rede/local).
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if (app_config('app.env', 'prod') === 'prod' || $devMode === false) {
            $currentEnvironment = 'servidor';
        } elseif ($lanIp !== '' && $httpHost !== '' && str_contains($httpHost, $lanIp)) {
            $currentEnvironment = 'rede';
        } else {
            $currentEnvironment = 'local';
        }

        $environments = [
            [
                'id'         => 'servidor',
                'name'       => 'Servidor (produção)',
                'url'        => rtrim($appUrl, '/') . '/api',
                'desc'       => 'Sistema publicado na internet. Use esta URL no APK final. Se ainda não configurou, defina APP_URL (ou a URL no config).',
                'is_current' => $currentEnvironment === 'servidor',
            ],
            [
                'id'         => 'rede',
                'name'       => 'Rede local',
                'url'        => $lanIp !== '' ? $apiUrl : '',
                'desc'       => 'Testando o app num celular na mesma rede Wi-Fi do servidor.',
                'is_current' => $currentEnvironment === 'rede',
            ],
            [
                'id'         => 'local',
                'name'       => 'Local (desenvolvimento)',
                'url'        => 'http://localhost:8080/api',
                'desc'       => 'Testando no PC. Emulador Android use http://10.0.2.2:8080/api.',
                'is_current' => $currentEnvironment === 'local',
            ],
        ];

        // Equipes: Laranja e Preta (a view monta a aba de credenciais).
        // $old tem prioridade sobre $teams (preserva o que o admin digitou
        // quando houve erro de validação). As senhas NUNCA vão em $old:
        // o formulário é preenchido pela senha atual vinda de $teams
        // (TeamRepository::byColor inclui a coluna `password` em texto puro).
        $teams = [
            'laranja' => self::teamViewData(TeamRepository::byColor('laranja')),
            'preta'   => self::teamViewData(TeamRepository::byColor('preta')),
        ];

        $content = View::render('configuracoes', [
            'config'    => SettingsRepository::all(),
            'old'       => $old,
            'errors'    => [],
            'lanIp'     => $lanIp,
            'systemUrl' => $systemUrl,
            'apiUrl'    => $apiUrl,
            'hasApk'    => $hasApk,
            'devMode'   => $devMode,
            'appUrl'    => $appUrl,
            'currentEnvironment' => $currentEnvironment,
            'environments'       => $environments,
            'teams'     => $teams,
            'treasureOrder' => (string) ($old['treasureOrder'] ?? SettingsRepository::get('treasureOrder', 'estabelecida')),
            'adminUsername' => (string) ($old['adminUsername'] ?? SettingsRepository::get('adminUsername', 'admin')),
        ]);

        $html = View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => 'configuracoes',
            'flash'    => flash_get(),
            'content'  => $content,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    public function update(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $siteName = trim((string) ($body['siteName'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $supportEmail = trim((string) ($body['supportEmail'] ?? ''));

        // Checkbox: presente no body => '1', ausente => '0'
        $soundEnabled = isset($body['soundEnabled']) ? '1' : '0';
        $animationEnabled = isset($body['animationEnabled']) ? '1' : '0';

        $itemsPerPage = (int) ($body['itemsPerPage'] ?? 0);

        // API & Desenvolvimento
        $apiBaseUrl = trim((string) ($body['apiBaseUrl'] ?? ''));
        $apiDevMode = isset($body['apiDevMode']) ? '1' : '0';

        // Equipes (Laranja e Preta): username obrigatório; senha opcional.
        // A senha vem PRÉ-PREENCHIDA com a atual (coluna `password`); se o
        // admin limpar o campo (ou enviar em branco), a atual é MANTIDA.
        // Quando preenchida, a senha é gravada em texto puro (`password`) e o
        // `password_hash` é regenerado — ambos sincronizados para o mesmo valor.
        // Só valida/persiste quando o formulário enviou campos de equipe (a aba
        // é adicionada pelo frontend; sem os campos, as credenciais atuais são
        // preservadas).
        $teamOrangeUsername = strtolower(trim((string) ($body['teamOrangeUsername'] ?? '')));
        $teamOrangePassword = trim((string) ($body['teamOrangePassword'] ?? ''));
        $teamBlackUsername  = strtolower(trim((string) ($body['teamBlackUsername'] ?? '')));
        $teamBlackPassword  = trim((string) ($body['teamBlackPassword'] ?? ''));

        // Jogo: ordem dos tesouros e credenciais do admin da API.
        $treasureOrder = (string) ($body['treasureOrder'] ?? 'estabelecida');
        $adminUsername = strtolower(trim((string) ($body['adminUsername'] ?? '')));
        $adminPassword = (string) ($body['adminPassword'] ?? '');

        $hasTeamFields = array_key_exists('teamOrangeUsername', $body)
            || array_key_exists('teamBlackUsername', $body);

        $errors = [];

        if ($siteName === '') {
            $errors[] = 'O nome do site é obrigatório.';
        }

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail de suporte válido.';
        }

        if ($itemsPerPage < 5 || $itemsPerPage > 100) {
            $errors[] = 'Itens por página deve estar entre 5 e 100.';
        }

        if ($apiBaseUrl !== '' && !$this->isValidHttpUrl($apiBaseUrl)) {
            $errors[] = 'A URL da API deve ser uma URL http(s) válida.';
        }

        if ($hasTeamFields) {
            $errors = array_merge($errors, $this->validateTeamCredentials(
                'Equipe Laranja',
                TeamRepository::byColor('laranja'),
                $teamOrangeUsername,
                $teamOrangePassword
            ));

            $errors = array_merge($errors, $this->validateTeamCredentials(
                'Equipe Preta',
                TeamRepository::byColor('preta'),
                $teamBlackUsername,
                $teamBlackPassword
            ));
        }

        // Jogo: validações da aba "Jogo".
        if (!in_array($treasureOrder, ['estabelecida', 'aleatorio'], true)) {
            $errors[] = 'A ordem dos tesouros é inválida.';
        }

        if ($adminUsername === '') {
            $errors[] = 'O usuário do admin (API) é obrigatório.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $adminUsername)) {
            $errors[] = 'O usuário do admin (API) deve ter de 3 a 50 caracteres (letras, números, _ . -).';
        }

        if ($adminPassword !== '' && strlen($adminPassword) < 6) {
            $errors[] = 'A senha do admin (API) deve ter no mínimo 6 caracteres.';
        }

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));

            $old = [
                'siteName'         => $siteName,
                'description'      => $description,
                'supportEmail'     => $supportEmail,
                'soundEnabled'     => $soundEnabled,
                'animationEnabled' => $animationEnabled,
                'itemsPerPage'     => (string) $itemsPerPage,
                'apiBaseUrl'       => $apiBaseUrl,
                'apiDevMode'       => $apiDevMode,
                'treasureOrder'    => $treasureOrder,
                'adminUsername'    => $adminUsername,
            ];

            // Equipes: preserva o username digitado. A senha NUNCA vai para
            // $old — o formulário é repreenchido pela senha atual ($teams),
            // pois campos de senha pré-preenchidos não podem "voltar" com o
            // valor enviado quando houve erro em outra parte do formulário.
            if ($hasTeamFields) {
                $old['teamOrangeUsername'] = $teamOrangeUsername;
                $old['teamBlackUsername']  = $teamBlackUsername;
            }

            $_SESSION['old'] = $old;

            redirect('/configuracoes');
        }

        SettingsRepository::update([
            'siteName'         => $siteName,
            'description'      => $description,
            'supportEmail'     => $supportEmail,
            'soundEnabled'     => $soundEnabled,
            'animationEnabled' => $animationEnabled,
            'itemsPerPage'     => (string) $itemsPerPage,
            'apiBaseUrl'       => $apiBaseUrl,
            'apiDevMode'       => $apiDevMode,
        ]);

        // Jogo: ordem dos tesouros e credenciais do admin da API.
        // adminPassword em branco mantém a senha atual.
        SettingsRepository::set('treasureOrder', $treasureOrder);
        SettingsRepository::set('adminUsername', $adminUsername);

        if ($adminPassword !== '') {
            SettingsRepository::set('adminPassword', $adminPassword);
        }

        // Equipes: atualiza credenciais.
        if ($hasTeamFields) {
            $this->saveTeamCredentials('laranja', $teamOrangeUsername, $teamOrangePassword);
            $this->saveTeamCredentials('preta', $teamBlackUsername, $teamBlackPassword);
        }

        flash_set('success', 'Configurações salvas com sucesso.');
        redirect('/configuracoes');
    }

    /**
     * Valida as credenciais de uma equipe.
     *
     * @param string $displayName  Nome de exibição usado nas mensagens
     * @param array<string, mixed>|null $currentTeam Equipe atual no banco
     * @param string $username     Username já normalizado (lowercase/trim)
     * @param string $password     Senha em texto puro (pode ser vazia)
     *
     * @return array<int, string> Mensagens de erro (vazio se tudo ok)
     */
    private function validateTeamCredentials(
        string $displayName,
        ?array $currentTeam,
        string $username,
        string $password
    ): array {
        $errors = [];

        if ($username === '') {
            $errors[] = 'Usuário da ' . $displayName . ' é obrigatório.';
        } elseif (!preg_match('/^[a-z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Usuário da ' . $displayName
                . ' deve ter de 3 a 50 caracteres (letras, números ou _).';
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'A senha da ' . $displayName
                . ' deve ter no mínimo 6 caracteres.';
        }

        // Unicidade: o username não pode pertencer a OUTRA equipe
        // (o próprio username atual é aceito sem erro).
        if ($username !== '') {
            $owner = TeamRepository::findByUsername($username);

            if ($owner !== null
                && ($currentTeam === null || (int) $owner['id'] !== (int) $currentTeam['id'])
            ) {
                $errors[] = 'O usuário da ' . $displayName
                    . ' já está em uso pela outra equipe.';
            }
        }

        return $errors;
    }

    /**
     * Persiste as credenciais de uma equipe (se ela existir).
     *
     * A senha em branco mantém a atual (não altera `password` nem
     * `password_hash`). Quando preenchida, grava o texto puro em `password`
     * e regenera o `password_hash` para o mesmo valor.
     */
    private function saveTeamCredentials(string $color, string $username, string $password): void
    {
        $team = TeamRepository::byColor($color);

        if ($team === null) {
            return;
        }

        $passwordFilled = $password !== '';

        TeamRepository::updateCredentials(
            (int) $team['id'],
            $username,
            $passwordFilled ? $password : null,
            $passwordFilled ? password_hash($password, PASSWORD_DEFAULT) : null
        );
    }

    /**
     * Monta os dados de uma equipe para a view (formato do contrato).
     *
     * @return array{id: int, name: string, username: string, password: string}|null
     */
    private static function teamViewData(?array $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id'       => (int) $team['id'],
            'name'     => (string) $team['name'],
            'username' => (string) $team['username'],
            'password' => (string) ($team['password'] ?? ''),
        ];
    }

    /**
     * Verifica se a string é uma URL http(s) válida.
     */
    private function isValidHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}