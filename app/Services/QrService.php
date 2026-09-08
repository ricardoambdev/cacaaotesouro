<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;
use Throwable;

/**
 * Geração e remoção das imagens de QR code dos tesouros.
 *
 * O fluxo do jogo usa SVG (generateSvg): o arquivo é gravado em
 * public/uploads/qr/tesouro_<id>.svg e servido pela web em
 * /uploads/qr/tesouro_<id>.svg.
 *
 * generate() (PNG) é mantido apenas por compatibilidade com fluxos antigos.
 */
final class QrService
{
    /**
     * Pasta de destino relativa à raiz do projeto.
     */
    private const QR_DIR = '/public/uploads/qr';

    /**
     * Gera o SVG do QR code do conteúdo e retorna o caminho web.
     *
     * Nome do arquivo fixo: tesouro_<treasureId>.svg (o mesmo arquivo é
     * sobrescrito quando o conteúdo muda — o QR é lido no local, não há
     * problema de cache do navegador).
     *
     * @throws RuntimeException em caso de falha na geração.
     */
    public static function generateSvg(string $content, int $treasureId): string
    {
        if (trim($content) === '') {
            throw new RuntimeException('QR code: o conteúdo a codificar está vazio.');
        }

        $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $dir = $projectRoot . self::QR_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('QR code: não foi possível criar a pasta ' . $dir . '.');
        }

        $filename = sprintf('tesouro_%d.svg', $treasureId);
        $filePath = $dir . '/' . $filename;

        $options = new QROptions([
            'eccLevel'    => QRCode::ECC_L,
            'outputType'  => QROutputInterface::MARKUP_SVG,
            'scale'       => 6,
            'imageBase64' => false, // escreve o SVG cru no arquivo
        ]);

        try {
            (new QRCode($options))->render($content, $filePath);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'QR code: falha ao gerar o SVG. ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_file($filePath)) {
            throw new RuntimeException('QR code: o arquivo ' . $filePath . ' não foi criado.');
        }

        return '/uploads/qr/' . $filename;
    }

    /**
     * Gera o PNG do QR code (compatibilidade com fluxos antigos).
     *
     * Nome do arquivo: tesouro_<id>_<8 chars de hash>.png.
     *
     * @throws RuntimeException em caso de falha na geração.
     */
    public static function generate(string $content, int $treasureId): string
    {
        if (trim($content) === '') {
            throw new RuntimeException('QR code: o conteúdo a codificar está vazio.');
        }

        if (!extension_loaded('gd')) {
            throw new RuntimeException('QR code: a extensão GD do PHP não está disponível neste servidor.');
        }

        $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $dir = $projectRoot . self::QR_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('QR code: não foi possível criar a pasta ' . $dir . '.');
        }

        $hash = substr(hash('sha256', $content), 0, 8);
        $filename = sprintf('tesouro_%d_%s.png', $treasureId, $hash);
        $filePath = $dir . '/' . $filename;

        $options = new QROptions([
            'eccLevel'    => QRCode::ECC_L,
            'outputType'  => QROutputInterface::GDIMAGE_PNG,
            'scale'       => 8,
            'imageBase64' => false,
        ]);

        try {
            (new QRCode($options))->render($content, $filePath);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'QR code: falha ao gerar a imagem. ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_file($filePath)) {
            throw new RuntimeException('QR code: o arquivo ' . $filePath . ' não foi criado.');
        }

        return '/uploads/qr/' . $filename;
    }

    /**
     * Apaga o arquivo apontado pelo caminho web (ex.: /uploads/qr/tesouro_1.svg).
     *
     * O caminho web /uploads/... corresponde fisicamente a
     * <raiz>/public/uploads/...; ignoramos erros (arquivo inexistente ou
     * sem permissão não é problema).
     */
    public static function remove(string $webPath): void
    {
        if (trim($webPath) === '') {
            return;
        }

        $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $file = $projectRoot . '/public/' . ltrim($webPath, '/');

        if (is_file($file)) {
            @unlink($file);
        }
    }
}