<?php

declare(strict_types=1);

use App\Services\FormSchema\FormSchemaNormalizer;
use App\Services\Submissions\SubmissionPresenter;

function presentAnswers(array $data): array
{
    $schema = app(FormSchemaNormalizer::class)->normalize(['fields' => [
        ['id' => 'nome', 'type' => 'short_text', 'label' => 'Nome'],
        ['id' => 'relato', 'type' => 'long_text', 'label' => 'Relato'],
        ['id' => 'nascimento', 'type' => 'date', 'label' => 'Nascimento'],
        ['id' => 'cor', 'type' => 'single_choice', 'label' => 'Cor', 'options' => [['value' => 'azul', 'label' => 'Azul-claro']]],
        ['id' => 'frutas', 'type' => 'multiple_choice', 'label' => 'Frutas', 'options' => [['value' => 'maca', 'label' => 'Maçã'], ['value' => 'uva', 'label' => 'Uva']]],
        ['id' => 'anexo', 'type' => 'file', 'label' => 'Anexo', 'file' => ['accept' => ['pdf']]],
    ]]);

    return (new SubmissionPresenter)->present($schema, $data);
}

it('apresenta as respostas na ordem do schema, com rótulos das opções', function () {
    $rows = presentAnswers([
        'nome' => 'Maria',
        'relato' => "linha 1\nlinha 2",
        'nascimento' => '1990-05-20',
        'cor' => 'azul',
        'frutas' => ['uva', 'maca'],
    ]);

    expect(array_column($rows, 'label'))->toBe(['Nome', 'Relato', 'Nascimento', 'Cor', 'Frutas'])
        ->and(array_column($rows, 'value'))->toBe(['Maria', "linha 1\nlinha 2", '20/05/1990', 'Azul-claro', 'Uva, Maçã'])
        ->and($rows[1]['multiline'])->toBeTrue();
});

it('marca campos não respondidos', function () {
    $rows = presentAnswers(['nome' => 'Maria']);

    expect($rows[1])->toBe(['field_id' => 'relato', 'label' => 'Relato', 'value' => SubmissionPresenter::NOT_ANSWERED, 'multiline' => true, 'answered' => false]);
});

it('deixa anexos de fora (apresentados à parte)', function () {
    expect(array_column(presentAnswers([]), 'label'))->not->toContain('Anexo');
});

it('mostra o valor bruto se a opção não existir mais', function () {
    expect(presentAnswers(['cor' => 'verde'])[3]['value'])->toBe('verde');
});
