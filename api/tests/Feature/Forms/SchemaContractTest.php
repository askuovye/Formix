<?php

declare(strict_types=1);

use App\Models\Form;
use App\Services\FormSchema\FormConsistency;
use App\Services\FormSchema\FormSchemaRules;
use App\Services\FormSchema\FormTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $schema
 */
function putSchema(Form $form, array $schema): TestResponse
{
    return test()->actingAs($form->user)->patchJson("/api/v1/me/forms/{$form->id}", ['schema' => $schema]);
}

function ratingField(string $id = 'nota'): array
{
    return ['id' => $id, 'type' => 'rating', 'label' => 'Nota', 'rating' => ['max' => 5]];
}

function conditionalField(array $when, string $action = 'show'): array
{
    return ['id' => 'motivo', 'type' => 'long_text', 'label' => 'Motivo', 'visibility' => ['action' => $action, 'when' => $when]];
}

it('salva blocos, estrelas, escala e condição já normalizados', function () {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => [
        ['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => '  Sobre o atendimento  '],
        ratingField(),
        ['id' => 'quebra', 'kind' => 'block', 'type' => 'page_break'],
        ['id' => 'recomenda', 'type' => 'scale', 'label' => 'Recomendaria?', 'scale' => ['min' => 0, 'max' => 10, 'max_label' => 'Com certeza']],
        conditionalField(['field' => 'nota', 'operator' => 'lte', 'value' => 2]),
        ['id' => 'divisor', 'kind' => 'block', 'type' => 'divider'],
        ['id' => 'aviso', 'kind' => 'block', 'type' => 'text', 'text' => 'Obrigado por responder.'],
    ]])->assertOk();

    $fields = $form->fresh()->schema['fields'];

    expect($fields[0])->toBe(['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Sobre o atendimento'])
        ->and($fields[1]['kind'])->toBe('input')
        ->and($fields[1]['rating'])->toBe(['max' => 5])
        ->and($fields[2])->toBe(['id' => 'quebra', 'kind' => 'block', 'type' => 'page_break'])
        ->and($fields[3]['scale'])->toBe(['min' => 0, 'max' => 10, 'min_label' => null, 'max_label' => 'Com certeza'])
        ->and($fields[4]['visibility'])->toBe(['action' => 'show', 'when' => ['field' => 'nota', 'operator' => 'lte', 'value' => 2]])
        ->and($fields[3]['visibility'])->toBeNull();
});

it('usa 5 estrelas por padrão e completa o tema com o padrão Clássico', function () {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => [['id' => 'nota', 'type' => 'rating', 'label' => 'Nota']]])->assertOk();

    expect($form->fresh()->schema['fields'][0]['rating'])->toBe(['max' => 5])
        ->and($form->fresh()->schema['theme'])->toBe(FormTheme::DEFAULTS);
});

it('rejeita blocos e configurações inválidas', function (array $fields, string $errorKey) {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => $fields])->assertUnprocessable()->assertJsonValidationErrors([$errorKey]);
})->with([
    'título de seção sem texto' => [[['id' => 'secao', 'kind' => 'block', 'type' => 'section_title']], 'schema.fields.0.text'],
    'divisor com texto' => [[['id' => 'divisor', 'kind' => 'block', 'type' => 'divider', 'text' => 'x']], 'schema.fields.0.text'],
    'bloco com rótulo' => [[['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Ok', 'label' => 'x']], 'schema.fields.0.label'],
    'bloco obrigatório' => [[['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Ok', 'required' => true]], 'schema.fields.0.required'],
    'bloco com regra' => [[['id' => 'secao', 'kind' => 'block', 'type' => 'text', 'text' => 'Ok', 'rules' => [['type' => 'min_length', 'value' => 1]]]], 'schema.fields.0.rules'],
    'bloco desconhecido' => [[['id' => 'x', 'kind' => 'block', 'type' => 'video']], 'schema.fields.0.type'],
    'kind desconhecido' => [[['id' => 'x', 'kind' => 'widget', 'type' => 'short_text', 'label' => 'X']], 'schema.fields.0.kind'],
    'quebra de etapa no início' => [[['id' => 'q', 'kind' => 'block', 'type' => 'page_break'], ratingField()], 'schema.fields.0.type'],
    'quebra de etapa no fim' => [[ratingField(), ['id' => 'q', 'kind' => 'block', 'type' => 'page_break']], 'schema.fields.1.type'],
    'quebras seguidas' => [[ratingField('a'), ['id' => 'q1', 'kind' => 'block', 'type' => 'page_break'], ['id' => 'q2', 'kind' => 'block', 'type' => 'page_break'], ratingField('b')], 'schema.fields.2.type'],
    'estrelas demais' => [[['id' => 'nota', 'type' => 'rating', 'label' => 'Nota', 'rating' => ['max' => 11]]], 'schema.fields.0.rating.max'],
    'estrelas de menos' => [[['id' => 'nota', 'type' => 'rating', 'label' => 'Nota', 'rating' => ['max' => 2]]], 'schema.fields.0.rating.max'],
    'escala sem configuração' => [[['id' => 'e', 'type' => 'scale', 'label' => 'E']], 'schema.fields.0.scale'],
    'escala invertida' => [[['id' => 'e', 'type' => 'scale', 'label' => 'E', 'scale' => ['min' => 5, 'max' => 3]]], 'schema.fields.0.scale.max'],
    'escala acima de 10' => [[['id' => 'e', 'type' => 'scale', 'label' => 'E', 'scale' => ['min' => 0, 'max' => 20]]], 'schema.fields.0.scale.max'],
    'configuração de estrelas em texto' => [[['id' => 't', 'type' => 'short_text', 'label' => 'T', 'rating' => ['max' => 5]]], 'schema.fields.0.rating'],
]);

it('rejeita condições incoerentes com o schema', function (array $fields, string $message) {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => $fields])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => in_array($message, $errors['schema.fields.1.visibility'] ?? [], strict: true));
})->with([
    'campo inexistente' => [[ratingField(), conditionalField(['field' => 'nao_existe', 'operator' => 'answered'])], 'A condição usa um campo que não existe.'],
    'o próprio campo' => [[ratingField(), conditionalField(['field' => 'motivo', 'operator' => 'answered'])], 'A condição precisa usar um campo que vem antes deste.'],
    'bloco como origem' => [[['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Ok'], conditionalField(['field' => 'secao', 'operator' => 'answered'])], 'A condição não pode usar blocos nem campos de arquivo.'],
    'arquivo como origem' => [[['id' => 'doc', 'type' => 'file', 'label' => 'Doc', 'file' => ['accept' => ['pdf']]], conditionalField(['field' => 'doc', 'operator' => 'answered'])], 'A condição não pode usar blocos nem campos de arquivo.'],
    'operador de lista em número' => [[ratingField(), conditionalField(['field' => 'nota', 'operator' => 'includes', 'value' => 2])], 'Esse operador não vale para o campo escolhido na condição.'],
    'operador numérico em texto' => [[['id' => 'nome', 'type' => 'short_text', 'label' => 'Nome'], conditionalField(['field' => 'nome', 'operator' => 'lte', 'value' => 'x'])], 'Esse operador não vale para o campo escolhido na condição.'],
    'texto comparado a número' => [[ratingField(), conditionalField(['field' => 'nota', 'operator' => 'lte', 'value' => 'dois'])], 'O valor da condição não combina com o campo escolhido.'],
    'opção que não existe' => [[['id' => 'cor', 'type' => 'single_choice', 'label' => 'Cor', 'options' => [['value' => 'azul', 'label' => 'Azul']]], conditionalField(['field' => 'cor', 'operator' => 'eq', 'value' => 'roxo'])], 'O valor da condição não combina com o campo escolhido.'],
    'valor em "respondido"' => [[ratingField(), conditionalField(['field' => 'nota', 'operator' => 'answered', 'value' => 3])], 'Esse operador não usa valor.'],
    'ação inválida' => [[ratingField(), conditionalField(['field' => 'nota', 'operator' => 'answered'], 'toggle')], 'A condição precisa dizer se o campo é mostrado ou ocultado.'],
]);

it('torna ciclos impossíveis: a origem precisa vir antes', function () {
    $form = Form::factory()->create();

    // A depende de B e B depende de A.
    putSchema($form, ['fields' => [
        ['id' => 'a', 'type' => 'short_text', 'label' => 'A', 'visibility' => ['action' => 'show', 'when' => ['field' => 'b', 'operator' => 'answered']]],
        ['id' => 'b', 'type' => 'short_text', 'label' => 'B', 'visibility' => ['action' => 'show', 'when' => ['field' => 'a', 'operator' => 'answered']]],
    ]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schema.fields.0.visibility']);
});

it('limita o número de condições por formulário', function () {
    $form = Form::factory()->create();
    $fields = [ratingField('origem')];

    foreach (range(1, FormSchemaRules::MAX_VISIBILITY_RULES + 1) as $i) {
        $fields[] = ['id' => "campo_{$i}", 'type' => 'short_text', 'label' => "Campo {$i}", 'visibility' => ['action' => 'show', 'when' => ['field' => 'origem', 'operator' => 'answered']]];
    }

    putSchema($form, ['fields' => $fields])->assertUnprocessable()->assertJsonValidationErrors(['schema.fields']);
});

it('valida o tema', function (array $theme, string $errorKey) {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => [ratingField()], 'theme' => $theme])->assertUnprocessable()->assertJsonValidationErrors([$errorKey]);
})->with([
    'preset desconhecido' => [['preset' => 'neon'], 'schema.theme.preset'],
    'cor fora do formato' => [['colors' => ['primary' => 'red']], 'schema.theme.colors.primary'],
    'cor com 3 dígitos' => [['colors' => ['text' => '#fff']], 'schema.theme.colors.text'],
    'fonte de CDN' => [['font' => 'roboto'], 'schema.theme.font'],
    'opacidade acima do limite' => [['cover' => ['asset_id' => '01jbsubmission0000000000000', 'overlay_opacity' => 90]], 'schema.theme.cover.overlay_opacity'],
    'capa sem imagem' => [['cover' => ['fit' => 'cover']], 'schema.theme.cover.asset_id'],
]);

it('salva o tema personalizado, com cores em maiúsculas', function () {
    $form = Form::factory()->create();

    putSchema($form, ['fields' => [ratingField()], 'theme' => [
        'preset' => 'custom', 'colors' => ['primary' => '#2f5bea', 'background' => '#0b0d10', 'surface' => '#14171c', 'text' => '#e6e8eb'],
        'radius' => 'large', 'font' => 'serif', 'button_style' => 'outline', 'alignment' => 'center',
    ]])->assertOk();

    expect($form->fresh()->schema['theme'])->toMatchArray([
        'preset' => 'custom',
        'colors' => ['primary' => '#2F5BEA', 'background' => '#0B0D10', 'surface' => '#14171C', 'text' => '#E6E8EB'],
        'radius' => 'large', 'font' => 'serif', 'button_style' => 'outline', 'alignment' => 'center', 'cover' => null, 'logo' => null,
    ]);
});

it('não publica formulário só com blocos', function () {
    $form = Form::factory()->create();
    putSchema($form, ['fields' => [['id' => 'secao', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Só título']]])->assertOk();

    $this->postJson("/api/v1/me/forms/{$form->id}/publish")
        ->assertUnprocessable()
        ->assertJsonPath('errors.schema.0', FormConsistency::NO_FIELDS_MESSAGE);
});

it('mostra o número de respostas e a última atividade na listagem', function () {
    $form = publishedForm();
    $this->travelTo('2026-10-20 12:00:00');
    $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])->assertCreated();
    $this->travelTo('2026-10-21 15:30:00');
    $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])->assertCreated();
    Form::factory()->for($form->user)->create();

    $response = $this->actingAs($form->user)->getJson('/api/v1/me/forms')->assertOk();
    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows[$form->id]['submissions_count'])->toBe(2)
        ->and($rows[$form->id]['last_submission_at'])->toBe('2026-10-21T15:30:00+00:00')
        ->and($rows->firstWhere('id', '!=', $form->id)['last_submission_at'])->toBeNull();
});
