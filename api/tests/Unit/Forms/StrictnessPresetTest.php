<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Enums\RuleType;
use App\Enums\StrictnessPreset;

/**
 * @return list<string>
 */
function presetRuleTypes(StrictnessPreset $preset, FieldType $field): array
{
    return array_column($preset->rulesFor($field), 'type');
}

it('expande cada nível com as regras esperadas', function (StrictnessPreset $preset, FieldType $field, array $expected) {
    expect(presetRuleTypes($preset, $field))->toBe($expected);
})->with([
    'leve' => [StrictnessPreset::Leve, FieldType::ShortText, ['no_only_symbols', 'no_repeated_chars']],
    'médio' => [StrictnessPreset::Medio, FieldType::ShortText, ['no_only_symbols', 'no_repeated_chars', 'no_sequences', 'min_distinct_chars', 'blocklist']],
    'estrito em texto curto' => [StrictnessPreset::Estrito, FieldType::ShortText, ['no_only_symbols', 'no_repeated_chars', 'no_sequences', 'min_distinct_chars', 'blocklist']],
    'estrito em texto longo' => [StrictnessPreset::Estrito, FieldType::LongText, ['no_only_symbols', 'no_repeated_chars', 'no_sequences', 'min_distinct_chars', 'blocklist', 'min_words']],
]);

it('fica mais rígido a cada nível', function () {
    $threshold = fn (StrictnessPreset $preset): float => collect($preset->rulesFor(FieldType::ShortText))->firstWhere('type', 'no_repeated_chars')['threshold'];
    $distinct = fn (StrictnessPreset $preset): int => collect($preset->rulesFor(FieldType::ShortText))->firstWhere('type', 'min_distinct_chars')['value'];

    expect($threshold(StrictnessPreset::Estrito))->toBeLessThan($threshold(StrictnessPreset::Leve))
        ->and($distinct(StrictnessPreset::Estrito))->toBeGreaterThan($distinct(StrictnessPreset::Medio));
});

it('não gera regras para campos sem texto livre', function (FieldType $field) {
    expect(StrictnessPreset::Estrito->rulesFor($field))->toBe([]);
})->with([FieldType::Email, FieldType::Number, FieldType::Date, FieldType::SingleChoice, FieldType::MultipleChoice, FieldType::File]);

it('só gera regras aplicáveis ao tipo do campo', function (StrictnessPreset $preset, FieldType $field) {
    foreach ($preset->rulesFor($field) as $rule) {
        expect(RuleType::from($rule['type'])->appliesTo($field))->toBeTrue();
    }
})->with(StrictnessPreset::cases())->with([FieldType::ShortText, FieldType::LongText]);

it('tem uma blocklist padrão dentro dos limites da regra', function () {
    expect(count(StrictnessPreset::DEFAULT_BLOCKLIST))->toBeLessThanOrEqual(RuleType::BLOCKLIST_MAX_ITEMS)
        ->and(StrictnessPreset::DEFAULT_BLOCKLIST)->each->toBeString();
});
