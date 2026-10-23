<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;
use LaravelLegends\PtBrValidator\Rules\Cnpj;

/**
 * Dígitos verificadores pelo pt-br-validator, inclusive o CNPJ alfanumérico
 * (letras nas 12 primeiras posições, adotado pela Receita em 2026). Antes,
 * exige que só haja letras, dígitos e separadores (. / - espaço).
 */
final class CnpjRule implements AnswerRule
{
    public function passes(string $value, array $params): bool
    {
        return preg_match('/^[0-9A-Za-z.\/\-\s]+$/', $value) === 1
            && (new Cnpj)->passes('cnpj', $value);
    }

    public function message(array $params): string
    {
        return 'Informe um CNPJ válido.';
    }
}
