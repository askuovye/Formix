<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Enums\RuleType;
use App\Enums\StrictnessPreset;
use App\Services\FormSchema\FormSchemaNormalizer;
use App\Validation\FormSubmissionValidator;
use App\Validation\Rules\HeuristicRule;
use Illuminate\Validation\ValidationException;

/**
 * Schema como sai do banco: o mesmo de validFormSchema(), normalizado.
 *
 * @return array{fields: list<array<string, mixed>>}
 */
function publishedSchema(): array
{
    return app(FormSchemaNormalizer::class)->normalize(validFormSchema());
}

/**
 * @param  array<string, mixed>  $input
 * @return array<string, list<string>>
 */
function submissionErrors(array $input, ?array $schema = null): array
{
    try {
        app(FormSubmissionValidator::class)->validate($schema ?? publishedSchema(), $input);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * @return array<string, mixed>
 */
function validAnswers(): array
{
    return [
        'nome' => 'Maria Souza',
        'email' => 'maria@example.com',
        'relato' => 'Fui muito bem atendida pela equipe.',
        'nota' => 'boa',
    ];
}

it('aceita uma resposta válida e devolve os valores normalizados', function () {
    $answers = app(FormSubmissionValidator::class)->validate(publishedSchema(), [
        ...validAnswers(),
        'nome' => '  Maria    Souza ',
        'relato' => "Fui muito bem atendida.\r\n\r\n\r\nVoltarei!",
    ]);

    expect($answers)->toBe([
        'nome' => 'Maria Souza',
        'email' => 'maria@example.com',
        'relato' => "Fui muito bem atendida.\n\nVoltarei!",
        'nota' => 'boa',
    ]);
});

it('ignora chaves que não são campos do formulário', function () {
    $answers = app(FormSubmissionValidator::class)->validate(publishedSchema(), [
        ...validAnswers(),
        'is_admin' => true,
        'user_id' => '01JB0000000000000000000000',
    ]);

    expect($answers)->not->toHaveKeys(['is_admin', 'user_id']);
});

it('deixa campos de arquivo para o fluxo de anexos', function () {
    $answers = app(FormSubmissionValidator::class)->validate(publishedSchema(), validAnswers());

    expect($answers)->not->toHaveKey('comprovante');
});

it('devolve campos opcionais não respondidos como nulos', function () {
    $answers = app(FormSubmissionValidator::class)->validate(publishedSchema(), [
        'nome' => 'Maria Souza',
        'email' => 'maria@example.com',
    ]);

    expect($answers['relato'])->toBeNull()
        ->and($answers['nota'])->toBeNull();
});

it('aponta os erros pelo id do campo, com o rótulo na mensagem', function () {
    $errors = submissionErrors(['nome' => '', 'email' => 'invalido']);

    expect($errors)->toHaveKeys(['nome', 'email'])
        ->and($errors['nome'])->toBe(['O campo Nome completo é obrigatório.'])
        ->and($errors['email'])->toBe(['O campo Email deve ser um email válido.']);
});

it('mostra só o primeiro erro de cada campo', function () {
    expect(submissionErrors([...validAnswers(), 'nome' => 'aaaa'])['nome'])->toHaveCount(1);
});

it('usa a mensagem personalizada da regra quando o dono definiu uma', function () {
    expect(submissionErrors([...validAnswers(), 'relato' => 'Muito bom'])['relato'])
        ->toBe(['Escreva pelo menos 3 palavras.']);
});

it('usa a mensagem genérica nas regras heurísticas, sem revelar a regra', function () {
    $schema = ['fields' => [[
        'id' => 'nome', 'type' => 'short_text', 'label' => 'Nome', 'required' => true,
        'rules' => StrictnessPreset::Medio->rulesFor(FieldType::ShortText),
    ]]];

    foreach (['aaaaaaa', '123456', 'qwerty', 'teste', '...'] as $garbage) {
        expect(submissionErrors(['nome' => $garbage], $schema)['nome'] ?? null)
            ->toBe([HeuristicRule::MESSAGE], "\"{$garbage}\" deveria ser recusado");
    }
});

it('não deixa caracteres invisíveis burlarem as regras', function () {
    expect(submissionErrors([...validAnswers(), 'nome' => "a\u{200B}a\u{200B}a\u{200B}a\u{200B}a"]))->toHaveKey('nome');
});

it('recusa texto acima do limite sem rodar as regras', function () {
    $errors = submissionErrors([...validAnswers(), 'nome' => str_repeat('palavra ', 2000)]);

    expect($errors['nome'][0])->toContain('não pode ter mais de '.RuleType::MAX_TEXT_LENGTH.' caracteres');
});

it('recusa valores do tipo errado sem quebrar', function (mixed $value) {
    expect(submissionErrors([...validAnswers(), 'nome' => $value]))->toHaveKey('nome');
})->with([
    'lista' => [['Maria']],
    'objeto' => [['nome' => 'Maria']],
    'booleano' => [true],
    'número' => [42],
]);

it('valida no limite de tamanho em tempo curto, mesmo com todas as regras', function () {
    $schema = ['fields' => [[
        'id' => 'relato', 'type' => 'long_text', 'label' => 'Relato', 'required' => true,
        'rules' => [
            ...StrictnessPreset::Estrito->rulesFor(FieldType::LongText),
            ['type' => 'min_length', 'value' => 10],
            ['type' => 'max_length', 'value' => RuleType::MAX_TEXT_LENGTH],
        ],
    ]]];
    $text = mb_substr(str_repeat('Texto legítimo de exemplo com várias palavras diferentes. ', 200), 0, RuleType::MAX_TEXT_LENGTH);

    $start = hrtime(true);
    app(FormSubmissionValidator::class)->validate($schema, ['relato' => $text]);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    expect($elapsedMs)->toBeLessThan(500);
});
