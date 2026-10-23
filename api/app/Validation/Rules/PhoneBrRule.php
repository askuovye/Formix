<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;
use LaravelLegends\PtBrValidator\Rules\CelularComDdd;

/**
 * Telefone fixo ou celular com DDD, em qualquer máscara comum:
 * "(11) 98765-4321", "11987654321", "+55 11 3456-7890".
 *
 * Extrai os dígitos (aceitando o código do país 55), monta o formato
 * canônico "(DD) NNNNN-NNNN" e valida com o pt-br-validator. Rejeita ainda
 * DDD começando em 0, celular (11 dígitos) que não começa com 9 e número
 * com todos os dígitos iguais.
 */
final class PhoneBrRule implements AnswerRule
{
    private const COUNTRY_CODE = '55';

    public function passes(string $value, array $params): bool
    {
        if (preg_match('/^[\d\s()+\-.]+$/', $value) !== 1) {
            return false;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (in_array(strlen($digits), [12, 13], strict: true) && str_starts_with($digits, self::COUNTRY_CODE)) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        }

        $length = strlen($digits);

        if (! in_array($length, [10, 11], strict: true)
            || $digits[0] === '0'
            || ($length === 11 && $digits[2] !== '9')
            || count(array_unique(str_split($digits))) === 1) {
            return false;
        }

        $formatted = sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, $length - 6), substr($digits, -4));

        return (new CelularComDdd)->passes('telefone', $formatted);
    }

    public function message(array $params): string
    {
        return 'Informe um telefone válido com DDD.';
    }
}
