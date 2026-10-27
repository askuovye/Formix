<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Texto de busca: minúsculas e sem acentos, para que "joao" ache "João" com
 * um LIKE simples, igual no MariaDB e no SQLite dos testes.
 */
final class SearchText
{
    /** Cabe numa coluna TEXT com folga (64 KB). */
    public const MAX_LENGTH = 20_000;

    public static function normalize(string $text): string
    {
        $folded = Str::lower(Str::ascii($text));

        return trim((string) preg_replace('/\s+/', ' ', $folded));
    }

    /**
     * Junta os valores das respostas (texto, números e itens de múltipla escolha).
     *
     * @param  array<string, mixed>  $answers
     */
    public static function fromAnswers(array $answers): string
    {
        $values = [];

        array_walk_recursive($answers, function (mixed $value) use (&$values): void {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $values[] = (string) $value;
            }
        });

        return Str::limit(self::normalize(implode(' ', $values)), self::MAX_LENGTH, '');
    }

    /**
     * Filtro "contém" com os curingas do usuário (% e _) valendo como texto.
     * ESCAPE explícito: o SQLite não tem caractere de escape padrão no LIKE.
     *
     * @param  literal-string  $column
     */
    public static function whereContains(Builder $query, string $column, string $term): void
    {
        $escaped = strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']);

        $query->whereRaw("{$column} like ? escape '!'", ["%{$escaped}%"]);
    }
}
