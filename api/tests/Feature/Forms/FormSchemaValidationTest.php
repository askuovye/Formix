<?php

declare(strict_types=1);

use App\Enums\StrictnessPreset;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $schema
 */
function saveSchema(Form $form, array $schema): TestResponse
{
    return test()->actingAs($form->user)->patchJson("/api/v1/me/forms/{$form->id}", ['schema' => $schema]);
}

/**
 * Schema válido com o primeiro campo alterado.
 *
 * @param  array<string, mixed>  $changes
 * @return array{fields: list<array<string, mixed>>}
 */
function schemaWithFirstField(array $changes): array
{
    $schema = validFormSchema();
    $schema['fields'][0] = [...$schema['fields'][0], ...$changes];

    return $schema;
}

it('salva um schema válido já normalizado', function () {
    $form = Form::factory()->create();

    saveSchema($form, validFormSchema())->assertOk();

    $fields = $form->fresh()->schema['fields'];

    expect($fields)->toHaveCount(5)
        ->and($fields[0])->toBe([
            'id' => 'nome',
            'kind' => 'input',
            'type' => 'short_text',
            'label' => 'Nome completo',
            'help_text' => null,
            'required' => true,
            'placeholder' => null,
            'rules' => [['type' => 'min_length', 'value' => 5], ['type' => 'no_repeated_chars', 'threshold' => 0.8]],
            'visibility' => null,
        ])
        ->and($fields[2]['rules'][0]['message'])->toBe('Escreva pelo menos 3 palavras.')
        ->and($fields[3]['display'])->toBe('select')
        ->and($fields[4]['file'])->toBe(['accept' => ['pdf', 'image'], 'max_files' => 2, 'max_size_mb' => 5]);
});

it('preserva a ordem dos campos, opções e regras', function () {
    $form = Form::factory()->create();
    $schema = validFormSchema();
    $schema['fields'] = array_reverse($schema['fields']);
    $schema['fields'][1]['options'] = array_reverse($schema['fields'][1]['options']);

    saveSchema($form, $schema)->assertOk();

    $fields = $form->fresh()->schema['fields'];

    expect(array_column($fields, 'id'))->toBe(['comprovante', 'nota', 'relato', 'email', 'nome'])
        ->and(array_column($fields[1]['options'], 'value'))->toBe(['ruim', 'boa'])
        ->and(array_column($fields[4]['rules'], 'type'))->toBe(['min_length', 'no_repeated_chars']);
});

it('descarta chaves desconhecidas e aninhamentos arbitrários', function () {
    $form = Form::factory()->create();
    $schema = schemaWithFirstField(['onclick' => 'alert(1)', 'extra' => ['a' => ['b' => ['c' => 1]]]]);
    $schema['version'] = 99;
    $schema['fields'][0]['rules'][0]['executar'] = 'rm -rf /';

    saveSchema($form, $schema)->assertOk();

    $stored = $form->fresh()->schema;

    expect($stored)->toHaveKeys(['fields'])->not->toHaveKey('version')
        ->and($stored['fields'][0])->not->toHaveKeys(['onclick', 'extra'])
        ->and($stored['fields'][0]['rules'][0])->toBe(['type' => 'min_length', 'value' => 5]);
});

it('aplica valores padrão de exibição e de arquivo', function () {
    $form = Form::factory()->create();

    saveSchema($form, ['fields' => [
        ['id' => 'cor', 'type' => 'single_choice', 'label' => 'Cor', 'options' => [['value' => 'azul', 'label' => 'Azul']]],
        ['id' => 'foto', 'type' => 'file', 'label' => 'Foto', 'file' => ['accept' => ['image']]],
    ]])->assertOk();

    $fields = $form->fresh()->schema['fields'];

    expect($fields[0]['display'])->toBe('radio')
        ->and($fields[1]['file'])->toBe(['accept' => ['image'], 'max_files' => 1, 'max_size_mb' => 5]);
});

it('expande o preset de rigor em regras explícitas e não guarda o preset', function () {
    $form = Form::factory()->create();

    saveSchema($form, ['fields' => [
        ['id' => 'relato', 'type' => 'long_text', 'label' => 'Relato', 'strictness' => 'estrito'],
    ]])->assertOk();

    $field = $form->fresh()->schema['fields'][0];

    expect($field)->not->toHaveKey('strictness')
        ->and(array_column($field['rules'], 'type'))->toBe([
            'no_only_symbols', 'no_repeated_chars', 'no_sequences', 'min_distinct_chars', 'blocklist', 'min_words',
        ])
        ->and($field['rules'][1]['threshold'])->toBe(0.6)
        ->and($field['rules'][4]['values'])->toBe(StrictnessPreset::DEFAULT_BLOCKLIST);
});

it('mantém a regra do dono quando o preset traz a mesma regra', function () {
    $form = Form::factory()->create();

    saveSchema($form, schemaWithFirstField(['strictness' => 'leve']))->assertOk();

    $rules = $form->fresh()->schema['fields'][0]['rules'];
    $repeated = array_values(array_filter($rules, fn (array $rule): bool => $rule['type'] === 'no_repeated_chars'));

    expect($repeated)->toHaveCount(1)
        ->and($repeated[0]['threshold'])->toBe(0.8);
});

it('rejeita schemas inválidos apontando o campo com erro', function (array $schema, string $errorKey) {
    $form = Form::factory()->create();

    saveSchema($form, $schema)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);

    expect($form->fresh()->schema)->toBe(['fields' => []]);
})->with([
    'sem a lista de campos' => [[], 'schema.fields'],
    'identificador repetido' => [schemaWithFirstField(['id' => 'email']), 'schema.fields.0.id'],
    'identificador com acento' => [schemaWithFirstField(['id' => 'endereço']), 'schema.fields.0.id'],
    'identificador começando com número' => [schemaWithFirstField(['id' => '1nome']), 'schema.fields.0.id'],
    'tipo desconhecido' => [schemaWithFirstField(['type' => 'script']), 'schema.fields.0.type'],
    'rótulo vazio' => [schemaWithFirstField(['label' => '']), 'schema.fields.0.label'],
    'rótulo longo demais' => [schemaWithFirstField(['label' => str_repeat('a', 201)]), 'schema.fields.0.label'],
    'regra desconhecida' => [schemaWithFirstField(['rules' => [['type' => 'eval']]]), 'schema.fields.0.rules.0.type'],
    'regra repetida no campo' => [schemaWithFirstField(['rules' => [['type' => 'min_length', 'value' => 1], ['type' => 'min_length', 'value' => 2]]]), 'schema.fields.0.rules.1.type'],
    'regra sem parâmetro obrigatório' => [schemaWithFirstField(['rules' => [['type' => 'min_length']]]), 'schema.fields.0.rules.0.value'],
    'threshold fora do intervalo' => [schemaWithFirstField(['rules' => [['type' => 'no_repeated_chars', 'threshold' => 2]]]), 'schema.fields.0.rules.0.threshold'],
    'blocklist grande demais' => [schemaWithFirstField(['rules' => [['type' => 'blocklist', 'values' => array_map(fn (int $i): string => "item{$i}", range(1, 201))]]]), 'schema.fields.0.rules.0.values'],
    'item da blocklist longo demais' => [schemaWithFirstField(['rules' => [['type' => 'blocklist', 'values' => [str_repeat('x', 101)]]]]), 'schema.fields.0.rules.0.values.0'],
    'preset inexistente' => [schemaWithFirstField(['strictness' => 'maximo']), 'schema.fields.0.strictness'],
    'escolha sem opções' => [['fields' => [['id' => 'cor', 'type' => 'single_choice', 'label' => 'Cor']]], 'schema.fields.0.options'],
    'opção repetida' => [['fields' => [['id' => 'cor', 'type' => 'multiple_choice', 'label' => 'Cor', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]]]], 'schema.fields.0.options.1.value'],
    'opções em campo de texto' => [schemaWithFirstField(['options' => [['value' => 'a', 'label' => 'A']]]), 'schema.fields.0.options'],
    'exibição inválida' => [['fields' => [['id' => 'cor', 'type' => 'single_choice', 'label' => 'Cor', 'display' => 'slider', 'options' => [['value' => 'a', 'label' => 'A']]]]], 'schema.fields.0.display'],
    'arquivo sem configuração' => [['fields' => [['id' => 'doc', 'type' => 'file', 'label' => 'Doc']]], 'schema.fields.0.file'],
    'tipo de arquivo não permitido' => [['fields' => [['id' => 'doc', 'type' => 'file', 'label' => 'Doc', 'file' => ['accept' => ['svg']]]]], 'schema.fields.0.file.accept.0'],
    'arquivos demais' => [['fields' => [['id' => 'doc', 'type' => 'file', 'label' => 'Doc', 'file' => ['accept' => ['pdf'], 'max_files' => 11]]]], 'schema.fields.0.file.max_files'],
    'arquivo grande demais' => [['fields' => [['id' => 'doc', 'type' => 'file', 'label' => 'Doc', 'file' => ['accept' => ['pdf'], 'max_size_mb' => 50]]]], 'schema.fields.0.file.max_size_mb'],
    'campos demais' => [['fields' => array_map(fn (int $i): array => ['id' => "campo_{$i}", 'type' => 'short_text', 'label' => "Campo {$i}"], range(1, 101))], 'schema.fields'],
]);

it('rejeita regra que não se aplica ao tipo do campo', function (string $fieldType, string $ruleType) {
    $form = Form::factory()->create();

    saveSchema($form, ['fields' => [['id' => 'campo', 'type' => $fieldType, 'label' => 'Campo', 'rules' => [['type' => $ruleType]]]]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => str_contains($errors['schema.fields.0.rules.0.type'][0] ?? '', 'não pode ser usada'));
})->with([
    'cpf em número' => ['number', 'cpf'],
    'cep em texto longo' => ['long_text', 'cep'],
    'sem só símbolos em data' => ['date', 'no_only_symbols'],
    'mínimo de palavras em texto curto' => ['short_text', 'min_words'],
]);

it('rejeita schema serializado grande demais', function () {
    $form = Form::factory()->create();
    $longHelp = str_repeat('a', 500);
    $fields = array_map(fn (int $i): array => [
        'id' => "campo_{$i}", 'type' => 'single_choice', 'label' => str_repeat('r', 200), 'help_text' => $longHelp,
        'options' => array_map(fn (int $j): array => ['value' => "op{$j}", 'label' => str_repeat('o', 200)], range(1, 15)),
    ], range(1, 100));

    saveSchema($form, ['fields' => $fields])
        ->assertUnprocessable()
        ->assertJsonPath('errors.schema.0', UpdateFormRequest::SCHEMA_TOO_LARGE_MESSAGE);
});

it('usa nomes legíveis nas mensagens de erro', function () {
    $form = Form::factory()->create();

    saveSchema($form, schemaWithFirstField(['label' => '']))
        ->assertJsonPath('errors', fn (array $errors): bool => $errors['schema.fields.0.label'][0] === 'O campo rótulo do campo é obrigatório.');
});
