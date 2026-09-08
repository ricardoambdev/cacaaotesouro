<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingsRepository;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Painel principal (requer autenticação).
 */
final class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string, email: string} $user */
        $user = $_SESSION['user'];

        $content = View::render('dashboard', [
            'user'   => $user,
            'config' => SettingsRepository::all(),
        ]);

        $html = View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => 'painel',
            'flash'    => flash_get(),
            'content'  => $content,
        ]);

        $response->getBody()->write($html);

        return $response;
    }
}