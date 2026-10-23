<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;

/**
 * Palavra = trecho entre espaços/quebras de linha com ao menos uma letra ou dígito.
 * Pontuação solta ("bom - ok") não conta como palavra.
 */
final class MinWordsRule implements AnswerRule
{
    public function passes(string $value, array $params): bool
    {
        $tokens = preg_split('/\s+/u', $value, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($tokens, fn (string $token): bool => preg_match('/[\p{L}\p{N}]/u', $token) === 1);

        return count($words) >= (int) $params['value'];
    }

    public function message(array $params): string
    {
        return "Escreva pelo menos {$params['value']} palavras.";
    }
}
