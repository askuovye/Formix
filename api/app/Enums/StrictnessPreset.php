<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Atalho do builder: expandido em regras explícitas ao salvar (AGENTS.md §5).
 * O schema guardado nunca contém o preset.
 */
enum StrictnessPreset: string
{
    case Leve = 'leve';
    case Medio = 'medio';
    case Estrito = 'estrito';

    /**
     * Valores triviais que costumam ser digitados só para passar do campo.
     * Comparados com a resposta inteira normalizada, nunca como substring.
     */
    public const DEFAULT_BLOCKLIST = [
        'teste', 'test', 'asdf', 'qwerty', 'xxx', 'xxxx', 'aaa', 'abc', 'abcd',
        'n/a', 'na', 'nada', 'nenhum', 'sei la', 'sla', 'ok', '...', '-', '.',
    ];

    /**
     * Regras do preset para o tipo de campo; vazio para tipos sem texto livre.
     *
     * @return list<array<string, mixed>>
     */
    public function rulesFor(FieldType $field): array
    {
        if (! $field->isText()) {
            return [];
        }

        $rules = [
            ['type' => RuleType::NoOnlySymbols->value],
            ['type' => RuleType::NoRepeatedChars->value, 'threshold' => $this === self::Estrito ? 0.6 : 0.9],
        ];

        if ($this === self::Leve) {
            return $rules;
        }

        $rules[] = ['type' => RuleType::NoSequences->value];
        $rules[] = ['type' => RuleType::MinDistinctChars->value, 'value' => $this === self::Estrito ? 4 : 3];
        $rules[] = ['type' => RuleType::Blocklist->value, 'values' => self::DEFAULT_BLOCKLIST];

        if ($this === self::Estrito && $field === FieldType::LongText) {
            $rules[] = ['type' => RuleType::MinWords->value, 'value' => 2];
        }

        return $rules;
    }
}
