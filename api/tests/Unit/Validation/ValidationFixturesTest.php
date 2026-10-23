<?php

declare(strict_types=1);

use App\Enums\RuleType;
use App\Validation\FormSubmissionValidator;
use App\Validation\InputNormalizer;
use App\Validation\RuleRegistry;
use Illuminate\Validation\ValidationException;

/*
| Casos compartilhados com o front (fixtures/validation-cases.json).
| Um teste por caso, com nome legível: a falha aponta o caso exato.
*/

/**
 * @return array<string, array<int, mixed>>
 */
function fixtureCases(string $section): array
{
    $cases = [];

    foreach (validationFixtures()[$section] as $key => $value) {
        if ($section !== 'rules') {
            $cases[sprintf('#%02d %s', $key, $value['note'])] = [$value];

            continue;
        }

        foreach ($value as $index => $case) {
            $label = sprintf('%s #%02d %s → %s', $key, $index, json_encode($case['value'], JSON_UNESCAPED_UNICODE), $case['valid'] ? 'válido' : 'inválido');
            $cases[$label] = [$key, $case];
        }
    }

    return $cases;
}

it('tem a estrutura esperada pelos dois lados', function () {
    expect(validationFixtures())->toHaveKeys(['normalization', 'rules', 'fields']);
});

it('cobre todas as regras do catálogo com casos válidos e inválidos', function (RuleType $type) {
    $cases = validationFixtures()['rules'][$type->value] ?? [];

    expect(array_column($cases, 'valid'))->toContain(true)->toContain(false);
})->with(RuleType::cases());

it('normaliza a entrada', function (array $case) {
    expect((new InputNormalizer)->normalize($case['input'], $case['multiline']))->toBe($case['output']);
})->with(fixtureCases('normalization'));

it('aplica a regra', function (string $type, array $case) {
    $normalized = (new InputNormalizer)->normalize($case['value'], $case['multiline'] ?? false);

    expect(app(RuleRegistry::class)->for(RuleType::from($type))->passes($normalized, $case['params']))->toBe($case['valid']);
})->with(fixtureCases('rules'));

it('valida o tipo do campo', function (array $case) {
    $field = [
        'id' => 'campo',
        'label' => 'Campo',
        'rules' => [],
        ...$case['field'],
        'options' => array_map(fn (string $value): array => ['value' => $value, 'label' => $value], $case['field']['options'] ?? []),
    ];

    $validate = fn () => app(FormSubmissionValidator::class)->validate(['fields' => [$field]], ['campo' => $case['value']]);

    if (! $case['valid']) {
        expect($validate)->toThrow(ValidationException::class);

        return;
    }

    expect($validate()['campo'])->toBe($case['normalized']);
})->with(fixtureCases('fields'));
