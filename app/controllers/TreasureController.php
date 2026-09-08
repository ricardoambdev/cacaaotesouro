<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TeamRepository;
use App\Repositories\TreasureRepository;
use App\Services\QrService;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Cadastro e gestão de tesouros (requer autenticação).
 */
final class TreasureController
{
    // ------------------------------------------------------------------
    // Listagem
    // ------------------------------------------------------------------

    public function index(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string, username: string} $user */
        $user = $_SESSION['user'];

        $teams = TeamRepository::all();

        $treasures = array_map(static function (array $treasure) use ($teams): array {
            // found_at por equipe (via progressForTreasure).
            $progress = GameRepository::progressForTreasure((int) $treasure['id']);

            $foundByTeam = [];

            foreach ($progress as $row) {
                $teamId = (int) $row['team_id'];

                $color = $teams[$teamId]['color'] ?? null;

                if ($color === null) {
                    continue;
                }

                $foundByTeam[$color] = [
                    'found_at'       => $row['found_at'] ?? null,
                    'riddle_correct' => (int) ($row['riddle_correct'] ?? 0),
                    'assigned_riddle'=> $row['assigned_riddle'] ?? null,
                ];
            }

            $treasure['progress'] = $foundByTeam;

            return $treasure;
        }, TreasureRepository::all());

        $game = [
            'treasureOrder' => (string) SettingsRepository::get('treasureOrder', 'estabelecida'),
            'gameActive'    => (string) SettingsRepository::get('gameActive', '0'),
            'winnerTeamId'  => (string) SettingsRepository::get('winnerTeamId', ''),
            'winner'        => null,
        ];

        if ($game['winnerTeamId'] !== '') {
            $winnerTeam = $teams[(int) $game['winnerTeamId']] ?? null;

            if ($winnerTeam !== null) {
                $game['winner'] = [
                    'id'    => (int) $winnerTeam['id'],
                    'name'  => (string) $winnerTeam['name'],
                    'color' => (string) $winnerTeam['color'],
                ];
            }
        }

        $content = View::render('tesouros', [
            'treasures' => $treasures,
            'teams'     => $teams,
            'game'      => $game,
        ]);

        $response->getBody()->write($this->renderLayout($content, $user));

        return $response;
    }

    // ------------------------------------------------------------------
    // Formulários
    // ------------------------------------------------------------------

    public function createForm(Request $request, Response $response): Response
    {
        /** @var array{id: int, name: string, username: string} $user */
        $user = $_SESSION['user'];

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $response->getBody()->write($this->renderLayout(
            $this->renderForm([
                'title'    => 'Cadastrar tesouro',
                'action'   => '/tesouros/novo',
                'treasure' => null,
                'old'      => $old,
                'errors'   => [],
            ]),
            $user
        ));

        return $response;
    }

    public function editForm(Request $request, Response $response, array $args): Response
    {
        /** @var array{id: int, name: string, username: string} $user */
        $user = $_SESSION['user'];

        $id = (int) ($args['id'] ?? 0);
        $treasure = TreasureRepository::find($id);

        if ($treasure === null) {
            flash_set('error', 'Tesouro não encontrado.');
            redirect('/tesouros');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $response->getBody()->write($this->renderLayout(
            $this->renderForm([
                'title'    => 'Editar tesouro',
                'action'   => '/tesouros/' . $id . '/editar',
                'treasure' => $treasure,
                'old'      => $old,
                'errors'   => [],
            ]),
            $user
        ));

        return $response;
    }

    // ------------------------------------------------------------------
    // Gravação
    // ------------------------------------------------------------------

    public function store(Request $request, Response $response): Response
    {
        $data = $this->extract($request);
        $errors = $this->validate($data);

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            $_SESSION['old'] = $data;
            redirect('/tesouros/novo');
        }

        // O conteúdo do QR code NÃO vem do formulário: é gerado
        // automaticamente como um código aleatório alfanumérico de 20 chars.
        $data['qr_content'] = random_alnum(20);
        $data['sort_order'] = TreasureRepository::nextSortOrder();
        $data['active'] = 0; // só ativa quando o admin confirmar a coordenada no app
        $data['qr_svg_path'] = '';

        $id = TreasureRepository::create($data);

        // Gera o QR SVG com o conteúdo recém-criado.
        try {
            $qrPath = QrService::generateSvg($data['qr_content'], $id);
            TreasureRepository::update($id, ['qr_svg_path' => $qrPath]);
        } catch (Throwable $e) {
            error_log('[Caça ao Tesouro] Falha ao gerar QR do tesouro ' . $id . ': ' . $e->getMessage());
            flash_set('error', 'Tesouro cadastrado, mas o QR code não pôde ser gerado.');
            redirect('/tesouros');
        }

        flash_set('success', 'Tesouro cadastrado com sucesso!');
        redirect('/tesouros');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $treasure = TreasureRepository::find($id);

        if ($treasure === null) {
            flash_set('error', 'Tesouro não encontrado.');
            redirect('/tesouros');
        }

        $data = $this->extract($request);
        $errors = $this->validate($data, $id);

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            $_SESSION['old'] = $data;
            redirect('/tesouros/' . $id . '/editar');
        }

        // Mantém qr_content e qr_svg_path existentes.
        $data['qr_content'] = (string) ($treasure['qr_content'] ?? '');
        if ($data['qr_content'] === '') {
            $data['qr_content'] = random_alnum(20);
        }
        $data['qr_svg_path'] = (string) ($treasure['qr_svg_path'] ?? '');

        TreasureRepository::update($id, $data);

        // Regenera o QR SVG apenas se ainda não existir arquivo.
        $hasImage = $data['qr_svg_path'] !== ''
            && is_file(dirname(__DIR__, 2) . '/public/' . ltrim($data['qr_svg_path'], '/'));

        if (!$hasImage) {
            try {
                if ($data['qr_svg_path'] !== '') {
                    QrService::remove($data['qr_svg_path']);
                }

                $qrPath = QrService::generateSvg($data['qr_content'], $id);
                TreasureRepository::update($id, ['qr_svg_path' => $qrPath]);
            } catch (Throwable $e) {
                error_log('[Caça ao Tesouro] Falha ao gerar QR do tesouro ' . $id . ': ' . $e->getMessage());
                flash_set('error', 'Tesouro atualizado, mas o QR code não pôde ser regenerado.');
                redirect('/tesouros');
            }
        }

        flash_set('success', 'Tesouro atualizado com sucesso!');
        redirect('/tesouros');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $treasure = TreasureRepository::find($id);

        if ($treasure !== null) {
            $qrPath = (string) ($treasure['qr_svg_path'] ?? '');

            if ($qrPath !== '') {
                QrService::remove($qrPath);
            }

            TreasureRepository::delete($id);
        }

        flash_set('success', 'Tesouro excluído.');
        redirect('/tesouros');
    }

    /**
     * Reordena os tesouros (POST JSON: { ids: [5,2,9,...] }).
     *
     * A rota passa pelo CSRF (a view inclui o token; requisições JSON
     * devem enviá-lo no header X-CSRF-Token — ver csrf_verify()).
     */
    public function reorder(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $ids = $body['ids'] ?? [];

        if (!is_array($ids)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Lista de ids inválida.',
            ], 400);
        }

        $orderedIds = [];

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $orderedIds[] = (int) $id;
            }
        }

        if ($orderedIds === []) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Nenhum id válido recebido.',
            ], 400);
        }

        TreasureRepository::reorder($orderedIds);

        return $this->json($response, ['success' => true]);
    }

    // ------------------------------------------------------------------
    // Privados
    // ------------------------------------------------------------------

    /**
     * Normaliza os campos do formulário.
     *
     * NOTA: `qr_content` NÃO é lido do formulário — é gerado
     * automaticamente (random_alnum) no controller.
     *
     * @return array<string, string>
     */
    private function extract(Request $request): array
    {
        $body = (array) $request->getParsedBody();

        return [
            'code'        => strtoupper(trim((string) ($body['code'] ?? ''))),
            'name'        => trim((string) ($body['name'] ?? '')),
            'description' => trim((string) ($body['description'] ?? '')),
            'clue'        => trim((string) ($body['clue'] ?? '')),
            'riddle1'     => trim((string) ($body['riddle1'] ?? '')),
            'answer1'     => trim((string) ($body['answer1'] ?? '')),
            'riddle2'     => trim((string) ($body['riddle2'] ?? '')),
            'answer2'     => trim((string) ($body['answer2'] ?? '')),
        ];
    }

    /**
     * Validação das regras de negócio.
     *
     * @param array<string, string> $data
     * @param int|null              $ignoreId Id do tesouro sendo editado
     *                                        (para ignorar o próprio code na
     *                                        checagem de unicidade)
     *
     * @return array<int, string>
     */
    private function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        // code: obrigatório, 2-50 chars, único.
        if ($data['code'] === '') {
            $errors[] = 'O código do tesouro é obrigatório.';
        } elseif (strlen($data['code']) < 2 || strlen($data['code']) > 50) {
            $errors[] = 'O código deve ter de 2 a 50 caracteres.';
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $data['code'])) {
            $errors[] = 'O código deve conter apenas letras, números, _ ou -.';
        } else {
            $owner = TreasureRepository::findByCode($data['code']);

            if ($owner !== null && ($ignoreId === null || (int) $owner['id'] !== $ignoreId)) {
                $errors[] = 'Já existe um tesouro com o código "' . $data['code'] . '".';
            }
        }

        if ($data['name'] === '') {
            $errors[] = 'O nome do tesouro é obrigatório.';
        } elseif (mb_strlen($data['name']) > 190) {
            $errors[] = 'O nome deve ter no máximo 190 caracteres.';
        }

        foreach ([
            'description' => 'a descrição',
            'clue'        => 'a dica',
            'riddle1'     => 'a charada 1',
            'riddle2'     => 'a charada 2',
        ] as $field => $label) {
            if ($data[$field] === '') {
                $errors[] = 'Informe ' . $label . '.';
            }
        }

        foreach (['answer1' => 'da charada 1', 'answer2' => 'da charada 2'] as $field => $label) {
            if ($data[$field] === '') {
                $errors[] = 'Informe a resposta ' . $label . '.';
            } elseif (!preg_match('/^\d{4,8}$/', $data[$field])) {
                $errors[] = 'A resposta ' . $label . ' deve ter de 4 a 8 dígitos.';
            }
        }

        return $errors;
    }

    /**
     * Renderiza o formulário de tesouro dentro do layout autenticado.
     *
     * @param array<string, mixed> $data Dados passados à view tesouros_form
     */
    private function renderForm(array $data): string
    {
        return View::render('tesouros_form', $data);
    }

    /**
     * Envolve o conteúdo no layout autenticado com a seção "tesouros" ativa.
     *
     * @param array<string, mixed> $user
     */
    private function renderLayout(string $content, array $user): string
    {
        return View::render('layout', [
            'siteName' => (string) app_config('app.name', 'Caça ao Tesouro'),
            'user'     => $user,
            'active'   => 'tesouros',
            'flash'    => flash_get(),
            'content'  => $content,
        ]);
    }

    /**
     * Monta a resposta JSON.
     *
     * @param array<string, mixed> $data
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}