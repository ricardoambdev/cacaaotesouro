<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

/**
 * Lista negra de NOMES (apelidos) que não podem ser usados no app.
 *
 * Usada em dois momentos:
 *  - quando a equipe define/edita o nome do aparelho (`/api/team/name`);
 *  - quando o admin derruba um aparelho e escolhe jogar o nome na lista.
 *
 * A lista fica na setting `nameBlocklist` (uma palavra por linha) e o painel
 * permite editar. Há uma lista padrão com palavrões/xingamentos.
 */
final class NameBlocklist
{
    /**
     * Palavras padrão (usadas no primeiro boot e no botão "restaurar").
     *
     * @return array<int, string>
     */
    public static function defaultWords(): array
    {
        return [
            // ── Palavrões (linguagem obscena) ──────────────────────
            'caralho', 'porra', 'merda', 'bosta', 'puta', 'putaria', 'puto',
            'foder', 'fuder', 'fodase', 'fodido', 'fudido', 'cacete',
            'krl', 'kct', 'vsf', 'vtnc', 'pqp', 'fdp', 'filhodaputa',
            'arrombado', 'arrombada', 'escroto', 'escrota',
            // ── Xingamentos ────────────────────────────────────────
            'otario', 'otaria', 'babaca', 'idiota', 'imbecil', 'burro',
            'burra', 'estupido', 'estupida', 'retardado', 'retardada',
            'vagabunda', 'vagabundo', 'vadia', 'safada', 'safado',
            'piranha', 'prostituta', 'gp', 'corno', 'corna', 'chifrudo',
            'chifre', 'nojento', 'nojenta', 'verme', 'ratazana', 'lixo',
            'escoria', 'vagabundagem',
            // ── Ofensas de cunho sexual ────────────────────────────
            'viado', 'veado', 'bicha', 'boiola', 'traveco', 'sapatao',
            // ── Termos sexuais / conteúdo adulto ───────────────────
            'buceta', 'boceta', 'xoxota', 'pepeca', 'pinto', 'pica', 'pau',
            'rola', 'saco', 'cuzao', 'cuzinho', 'cu', 'quenga', 'punheta',
            'punhetinha', 'punheteiro', 'gozar', 'gozada', 'tesao', 'tarado',
            'sexo', 'sexy', 'nudes', 'porno', 'pornografia', 'orgia',
            'suruba', 'menage', 'traicao', 'chupa',
            // ── Aparência (usado para provocar) ────────────────────
            'gordo', 'gorda', 'gordao', 'baleia', 'magrelo', 'magrela',
            'feioso', 'feiosa',
            // ── Etnia / origem ─────────────────────────────────────
            'macaco', 'macaca', 'preto', 'preta', 'crioulo', 'crioula',
            'negao', 'negona', 'neguinho', 'favelado', 'favelada', 'pobre',
            // ── Deficiência / capacidade ───────────────────────────
            'aleijado', 'aleijada', 'deficiente', 'mongol', 'mongoloide',
            'analfabeto', 'analfabeta', 'fracassado', 'fracassada',
            // ── Ódio / política ────────────────────────────────────
            'nazista', 'hitler',
            // ── Religião (usado como xingamento) ───────────────────
            'demonio', 'satanas', 'capeta', 'diabo', 'feiticeira',
            // ── Drogas e apostas ───────────────────────────────────
            'maconha', 'cocaina', 'crack', 'droga', 'drogado', 'traficante',
            'maconheiro', 'noia', 'bebida', 'cerveja', 'cachaca', 'bebado',
            'cachaceiro', 'aposta', 'apostar', 'cassino',
            // ── Violência / ameaça ─────────────────────────────────
            'matar', 'morte', 'suicidio', 'arma', 'bullying',
        ];
    }

    /**
     * Palavras configuradas no painel (uma por linha).
     *
     * @return array<int, string>
     */
    public static function words(): array
    {
        $raw = (string) SettingsRepository::get('nameBlocklist', '');

        if (trim($raw) === '') {
            $raw = implode("\n", self::defaultWords());
        }

        $words = [];

        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $line) {
            $word = self::normalize(trim($line));

            if ($word !== '') {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    /**
     * O nome pode ser usado?
     */
    public static function isBlocked(string $name): bool
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return false;
        }

        $tokens = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        foreach (self::words() as $word) {
            // 1) palavra exata no nome (ex.: "cu" só bloqueia o nome "cu")
            if (in_array($word, $tokens, true)) {
                return true;
            }

            // 2) palavra longa "escondida" no meio (ex.: "viado123", "seumerda")
            if (mb_strlen($word) >= 4 && str_contains($normalized, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devolve a palavra bloqueada encontrada (ou '' se o nome estiver livre).
     */
    public static function blockedWord(string $name): string
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return '';
        }

        $tokens = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        foreach (self::words() as $word) {
            if (in_array($word, $tokens, true)) {
                return $word;
            }

            if (mb_strlen($word) >= 4 && str_contains($normalized, $word)) {
                return $word;
            }
        }

        return '';
    }

    /**
     * Adiciona uma palavra à lista (usada ao derrubar um aparelho).
     */
    public static function addWord(string $word): void
    {
        $word = self::normalize(trim($word));

        if ($word === '' || mb_strlen($word) < 2) {
            return;
        }

        $words = self::words();

        if (in_array($word, $words, true)) {
            return;
        }

        $words[] = $word;

        sort($words);

        SettingsRepository::set('nameBlocklist', implode("\n", $words));
    }

    /**
     * Normaliza para comparar: minúsculas e sem acentos.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
        $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

        return str_replace($from, $to, $text);
    }
}
