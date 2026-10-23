<?php

declare(strict_types=1);

namespace App\Validation\Rules;

/**
 * Rejeita respostas sem nenhuma letra ou dígito: "...", "!!!", "-", "?!".
 */
final class NoOnlySymbolsRule extends HeuristicRule
{
    public function passes(string $value, array $params): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $value) === 1;
    }
}
