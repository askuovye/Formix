<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;
use LaravelLegends\PtBrValidator\Rules\Cpf;

/**
 * Dígitos verificadores pelo pt-br-validator (AGENTS.md §5.7), que também
 * rejeita dígitos todos iguais ("111.111.111-11"). Antes, exige que só haja
 * dígitos e separadores: o pacote descarta qualquer outro caractere, e
 * "CPF abc 123.456.789-09" passaria.
 */
final class CpfRule implements AnswerRule
{
    public function passes(string $value, array $params): bool
    {
        return preg_match('/^[\d.\-\s]+$/', $value) === 1
            && (new Cpf)->passes('cpf', $value);
    }

    public function message(array $params): string
    {
        return 'Informe um CPF válido.';
    }
}
