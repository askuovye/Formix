<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;

/**
 * Conta caracteres Unicode (code points) do texto normalizado: "ação" tem 4.
 */
final class MinLengthRule implements AnswerRule
{
    public function passes(string $value, array $params): bool
    {
        return mb_strlen($value) >= (int) $params['value'];
    }

    public function message(array $params): string
    {
        return "Use pelo menos {$params['value']} caracteres.";
    }
}
