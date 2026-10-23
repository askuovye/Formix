<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;
use LaravelLegends\PtBrValidator\Rules\FormatoCep;

/**
 * CEP com ou sem máscara: "01310-100", "01.310-100", "01310100".
 * Monta o formato "NNNNN-NNN" e valida com o pt-br-validator; rejeita
 * também todos os dígitos iguais ("00000-000"), que não existem.
 */
final class CepRule implements AnswerRule
{
    public function passes(string $value, array $params): bool
    {
        if (preg_match('/^[\d.\-\s]+$/', $value) !== 1) {
            return false;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) !== 8 || count(array_unique(str_split($digits))) === 1) {
            return false;
        }

        return (new FormatoCep)->passes('cep', substr($digits, 0, 5).'-'.substr($digits, 5));
    }

    public function message(array $params): string
    {
        return 'Informe um CEP válido.';
    }
}
