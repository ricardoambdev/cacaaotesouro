<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Renderizador de views com templates PHP puros (sem Twig).
 *
 * Cada template vive em app/views/{$template}.php e recebe as variáveis
 * do array $data via extract().
 */
final class View
{
    /**
     * Renderiza um template e retorna o HTML gerado.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        $file = __DIR__ . '/views/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('View não encontrada: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}