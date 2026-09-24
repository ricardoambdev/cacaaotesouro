<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DecoyQrRepository;
use App\Services\QrService;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * QR Codes FALSOS ("iscas") no painel.
 *
 * A organização cria QR codes com mensagens que NÃO identificam tesouro
 * nenhum. Quando a equipe lê um deles no app, aparece a mensagem e o jogo
 * volta para a tela inicial.
 */
final class DecoyQrController
{
    /**
     * GET /qrcodes-falsos — lista + formulário para criar.
     */
    public function index(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string} $user */
        $user = $_SESSION['user'];

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $content = View::render('qrcodes_falsos', [
            'decoys' => DecoyQrRepository::all(),
            'old'    => $old,
        ]);

        $response->getBody()->write($this->renderLayout($content, $user));

        return $response;
    }

    /**
     * POST /qrcodes-falsos — cria um QR code falso com a mensagem.
     */
    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '') {
            flash_set('error', 'Escreva a mensagem que aparecerá para a equipe.');
            $_SESSION['old'] = ['message' => $message];
            redirect('/qrcodes-falsos');
        }

        if (mb_strlen($message) > 500) {
            flash_set('error', 'A mensagem deve ter no máximo 500 caracteres.');
            $_SESSION['old'] = ['message' => $message];
            redirect('/qrcodes-falsos');
        }

        $id = DecoyQrRepository::create($message);

        // Gera o SVG (mesmo padrão dos tesouros) para poder imprimir.
        try {
            $row = DecoyQrRepository::find($id);

            if ($row !== null) {
                $path = QrService::generateCustomSvg(
                    (string) $row['content'],
                    'falso_' . $id . '.svg'
                );
                DecoyQrRepository::updateQrPath($id, $path);
            }
        } catch (Throwable $e) {
            error_log('[Caça ao Tesouro] Falha ao gerar QR falso ' . $id . ': ' . $e->getMessage());
            flash_set('error', 'QR code criado, mas a imagem não pôde ser gerada.');
            redirect('/qrcodes-falsos');
        }

        flash_set('success', 'QR code falso criado! Baixe o SVG e esconda no caminho.');
        redirect('/qrcodes-falsos');
    }

    /**
     * POST /qrcodes-falsos/{id}/excluir
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $row = DecoyQrRepository::find($id);

        if ($row === null) {
            flash_set('error', 'QR code falso não encontrado.');
            redirect('/qrcodes-falsos');
        }

        // Apaga também o arquivo SVG.
        QrService::remove((string) ($row['qr_svg_path'] ?? ''));

        DecoyQrRepository::delete($id);

        flash_set('success', 'QR code falso excluído.');
        redirect('/qrcodes-falsos');
    }

    /**
     * Envolve o conteúdo no layout autenticado.
     *
     * @param array<string, mixed> $user
     */
    private function renderLayout(string $content, array $user): string
    {
        return View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => 'qrcodes-falsos',
            'flash'    => flash_get(),
            'content'  => $content,
        ]);
    }
}
