<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Autenticação: login, registro, logout e recuperação de senha.
 */
final class AuthController
{
    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    public function showLogin(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user'])) {
            redirect('/');
        }

        $old = $_SESSION['old'] ?? ['username' => ''];
        unset($_SESSION['old']);

        $content = View::render('login', [
            'error' => null,
            'old'   => $old,
        ]);

        $response->getBody()->write($this->renderAuthLayout($content));

        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $username = strtolower(trim((string) ($body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->failLogin($username);
        }

        $user = UserRepository::findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->failLogin($username);
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
        ];

        unset($_SESSION['old']);

        flash_set('success', 'Bem-vindo de volta, ' . (string) $user['name'] . '!');
        redirect('/');
    }

    // ------------------------------------------------------------------
    // Registro
    // ------------------------------------------------------------------

    public function showRegister(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user'])) {
            redirect('/');
        }

        $old = $_SESSION['old'] ?? ['name' => '', 'username' => ''];
        unset($_SESSION['old']);

        $content = View::render('register', [
            'error' => null,
            'old'   => $old,
        ]);

        $response->getBody()->write($this->renderAuthLayout($content));

        return $response;
    }

    public function register(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['name'] ?? ''));
        $username = strtolower(trim((string) ($body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $passwordConfirm = (string) ($body['password_confirm'] ?? '');

        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors[] = 'O nome deve ter pelo menos 2 caracteres.';
        }

        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Usuário deve ter de 3 a 30 caracteres (letras, números ou _).';
        } elseif (UserRepository::findByUsername($username) !== null) {
            $errors[] = 'Este usuário já está cadastrado.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'As senhas não conferem.';
        }

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            $_SESSION['old'] = ['name' => $name, 'username' => $username];
            redirect('/register');
        }

        $userId = UserRepository::create([
            'name'          => $name,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'       => $userId,
            'name'     => $name,
            'username' => $username,
        ];

        flash_set('success', 'Conta criada com sucesso!');
        redirect('/');
    }

    // ------------------------------------------------------------------
    // Logout
    // ------------------------------------------------------------------

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();

        redirect('/login');
    }

    // ------------------------------------------------------------------
    // Recuperação de senha
    // ------------------------------------------------------------------

    public function showRecover(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user'])) {
            redirect('/');
        }

        $query = $request->getQueryParams();

        // Em ambiente de desenvolvimento o link aparece na própria tela.
        $dev = (string) ($query['dev'] ?? '');
        $devLink = null;

        if ($dev !== '') {
            $devLink = rtrim((string) app_config('app.url', ''), '/') . '/redefinir?token=' . $dev;
        }

        $old = $_SESSION['old'] ?? ['username' => ''];
        unset($_SESSION['old']);

        $content = View::render('recuperar', [
            'error'   => null,
            'old'     => $old,
            'devLink' => $devLink,
        ]);

        $response->getBody()->write($this->renderAuthLayout($content));

        return $response;
    }

    public function recover(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $username = strtolower(trim((string) ($body['username'] ?? '')));

        if ($username === '') {
            flash_set('error', 'Informe seu usuário.');
            $_SESSION['old'] = ['username' => $username];
            redirect('/recuperar');
        }

        $user = UserRepository::findByUsername($username);

        // Mensagem genérica: não revela se o usuário existe ou não.
        flash_set('success', 'Se este usuário estiver cadastrado, enviaremos um link de recuperação.');

        if ($user === null) {
            redirect('/recuperar?enviado=1');
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // válido por 1 hora

        UserRepository::setResetToken((int) $user['id'], $token, $expires);

        $appUrl = rtrim((string) app_config('app.url', ''), '/');
        $link = $appUrl . '/redefinir?token=' . $token;

        if (app_config('app.env', 'dev') === 'prod') {
            $email = (string) ($user['email'] ?? '');

            if ($email !== '') {
                $this->sendRecoveryEmail($email, (string) $user['name'], $link, $appUrl);
            } else {
                // Sem e-mail cadastrado: apenas loga o link no servidor.
                error_log('[Caça ao Tesouro] Link de recuperação para ' . $username . ': ' . $link);
            }

            redirect('/recuperar?enviado=1');
        }

        // Ambiente de desenvolvimento: o link é exibido na própria tela.
        redirect('/recuperar?enviado=1&dev=' . $token);
    }

    public function showReset(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user'])) {
            redirect('/');
        }

        $query = $request->getQueryParams();
        $token = (string) ($query['token'] ?? '');

        if (!$this->resetTokenIsValid($token)) {
            flash_set('error', 'Link de recuperação inválido ou expirado.');
            redirect('/recuperar');
        }

        $content = View::render('redefinir', [
            'token'      => $token,
            'tokenValid' => true,
        ]);

        $response->getBody()->write($this->renderAuthLayout($content));

        return $response;
    }

    public function reset(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $token = (string) ($body['token'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $passwordConfirm = (string) ($body['password_confirm'] ?? '');

        $user = $this->findValidResetUser($token);

        if ($user === null) {
            flash_set('error', 'Link de recuperação inválido ou expirado.');
            redirect('/recuperar');
        }

        $errors = [];

        if (strlen($password) < 6) {
            $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'As senhas não conferem.';
        }

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            redirect('/redefinir?token=' . urlencode($token));
        }

        UserRepository::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));

        flash_set('success', 'Senha alterada com sucesso. Faça login.');
        redirect('/login');
    }

    // ------------------------------------------------------------------
    // Privados
    // ------------------------------------------------------------------

    /**
     * Falha de login: flash de erro genérico, preserva o usuário e volta.
     */
    private function failLogin(string $username): void
    {
        flash_set('error', 'Usuário ou senha inválidos.');
        $_SESSION['old'] = ['username' => $username];
        redirect('/login');
    }

    /**
     * Valida se o token de reset existe e ainda não expirou.
     */
    private function resetTokenIsValid(string $token): bool
    {
        return $this->findValidResetUser($token) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findValidResetUser(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $user = UserRepository::findByResetToken($token);

        if ($user === null) {
            return null;
        }

        $expires = $user['reset_expires'] ?? null;

        if ($expires === null || $expires === '') {
            return null;
        }

        $timestamp = strtotime((string) $expires);

        if ($timestamp === false || $timestamp <= time()) {
            return null;
        }

        return $user;
    }

    /**
     * Renderiza uma view dentro do layout público (auth_layout).
     */
    private function renderAuthLayout(string $content): string
    {
        return View::render('auth_layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'content'  => $content,
            'flash'    => flash_get(),
        ]);
    }

    /**
     * Envia o e-mail de recuperação via mail() (somente em produção).
     */
    private function sendRecoveryEmail(string $to, string $name, string $link, string $appUrl): void
    {
        $subject = 'Recuperação de senha - Caça ao Tesouro';

        $body = 'Olá, ' . $name . "!\n\n"
            . "Recebemos um pedido de recuperação de senha para a sua conta.\n\n"
            . 'Para redefinir a sua senha, acesse o link abaixo:' . "\n"
            . $link . "\n\n"
            . 'Este link é válido por 1 hora.' . "\n\n"
            . 'Se você não solicitou esta recuperação, ignore este e-mail.' . "\n\n"
            . "Atenciosamente,\nEquipe Caça ao Tesouro";

        $host = (string) (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');
        $from = 'no-reply@' . $host;

        $headers = 'From: Caça ao Tesouro <' . $from . '>' . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

        @mail($to, $subject, $body, $headers);
    }
}