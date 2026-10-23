<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catálogo de regras de validação (AGENTS.md §5). Aqui fica o **contrato** de
 * cada regra no schema (onde se aplica e quais parâmetros aceita); a lógica de
 * validação das respostas é do motor de validação.
 */
enum RuleType: string
{
    case MinLength = 'min_length';
    case MaxLength = 'max_length';
    case MinWords = 'min_words';
    case MinDistinctChars = 'min_distinct_chars';
    case NoRepeatedChars = 'no_repeated_chars';
    case NoSequences = 'no_sequences';
    case NoOnlySymbols = 'no_only_symbols';
    case Blocklist = 'blocklist';
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case PhoneBr = 'phone_br';
    case Cep = 'cep';

    public const MAX_TEXT_LENGTH = 10_000;

    public const BLOCKLIST_MAX_ITEMS = 200;

    public const BLOCKLIST_ITEM_MAX_LENGTH = 100;

    public function appliesTo(FieldType $field): bool
    {
        return match ($this) {
            self::MinLength, self::MaxLength, self::MinDistinctChars,
            self::NoRepeatedChars, self::NoSequences, self::NoOnlySymbols, self::Blocklist => $field->isText(),
            self::MinWords => $field === FieldType::LongText,
            self::Cpf, self::Cnpj, self::PhoneBr, self::Cep => $field === FieldType::ShortText,
        };
    }

    /**
     * Regras do Laravel para os parâmetros da regra, por chave.
     *
     * @return array<string, list<string>>
     */
    public function parameterRules(): array
    {
        return match ($this) {
            self::MinLength, self::MaxLength => ['value' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TEXT_LENGTH]],
            self::MinWords => ['value' => ['required', 'integer', 'min:1', 'max:500']],
            self::MinDistinctChars => ['value' => ['required', 'integer', 'min:1', 'max:50']],
            self::NoRepeatedChars => ['threshold' => ['required', 'numeric', 'min:0.5', 'max:1']],
            self::NoSequences => ['value' => ['sometimes', 'integer', 'min:3', 'max:20']],
            self::Blocklist => [
                'values' => ['required', 'array', 'min:1', 'max:'.self::BLOCKLIST_MAX_ITEMS],
                'values.*' => ['required', 'string', 'max:'.self::BLOCKLIST_ITEM_MAX_LENGTH, 'distinct:ignore_case'],
            ],
            self::NoOnlySymbols, self::Cpf, self::Cnpj, self::PhoneBr, self::Cep => [],
        };
    }

    /**
     * Chaves de parâmetro mantidas na normalização (as demais são descartadas).
     *
     * @return list<string>
     */
    public function parameterKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->parameterRules()),
            fn (string $key): bool => ! str_contains($key, '.'),
        ));
    }
}
