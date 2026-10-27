<?php

declare(strict_types=1);

use App\Actions\Forms\CloseForm;
use App\Actions\Forms\PublishForm;
use App\Data\FormSettings;
use App\Events\SubmissionEdited;
use App\Http\Requests\Submissions\UpdateSubmissionRequest;
use App\Models\Form;
use App\Models\Submission;
use App\Services\Submissions\EditTokens;
use App\Services\Submissions\RespondentEditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Envia uma resposta e devolve [id da resposta, token de edição].
 *
 * @return array{0: string, 1: string}
 */
function submitEditable(Form $form): array
{
    $receipt = test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])
        ->assertCreated()
        ->json('data');

    return [$receipt['id'], explode('#token=', (string) $receipt['edit_url'])[1]];
}

function editHeaders(?string $token): array
{
    return $token === null ? [] : [UpdateSubmissionRequest::EDIT_TOKEN_HEADER => $token];
}

function openForEdit(Form $form, string $id, ?string $token): TestResponse
{
    return test()->withHeaders(editHeaders($token))->getJson("/api/v1/forms/{$form->slug}/submissions/{$id}");
}

/**
 * @param  array<string, mixed>  $answers
 */
function saveEdit(Form $form, string $id, ?string $token, array $answers): TestResponse
{
    return test()->withHeaders(editHeaders($token))->putJson("/api/v1/forms/{$form->slug}/submissions/{$id}", ['answers' => $answers]);
}

it('não gera link de edição quando o dono não permitiu', function () {
    $form = publishedForm();

    $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])
        ->assertCreated()
        ->assertJsonPath('data.edit_url', null);

    expect(Submission::query()->sole()->edit_token_hash)->toBeNull();
});

it('gera link de edição válido por 4 horas, com o token no fragmento', function () {
    $this->freezeTime();
    $form = publishedForm(['allow_respondent_edit' => true]);

    $receipt = $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])
        ->assertCreated()
        ->json('data');

    [$id, $token] = [$receipt['id'], explode('#token=', $receipt['edit_url'])[1]];

    expect($receipt['edit_url'])->toBe("http://localhost:5173/f/{$form->slug}/respostas/{$id}/editar#token={$token}")
        ->and($receipt['editable_until'])->toBe(now()->addHours(4)->utc()->toIso8601String())
        ->and(Submission::query()->sole()->edit_token_hash)->toBe(app(EditTokens::class)->hash($token))
        ->and(json_encode(Submission::query()->sole()->getAttributes()))->not->toContain($token);
});

it('abre a resposta para edição com o token', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    openForEdit($form, $id, $token)
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.answers.nome', 'Maria Souza')
        ->assertJsonPath('data.schema.fields.0.id', 'nome');
});

it('responde 404 sem token ou com token errado', function (?string $token) {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id] = submitEditable($form);

    openForEdit($form, $id, $token)->assertNotFound();
    saveEdit($form, $id, $token, validSubmissionAnswers())->assertNotFound();
})->with(['sem token' => null, 'token errado' => 'token-qualquer-que-nao-confere']);

it('não abre resposta de outro formulário pelo link deste', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    $other = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    openForEdit($other, $id, $token)->assertNotFound();
});

it('salva a edição guardando a versão anterior', function () {
    Event::fake([SubmissionEdited::class]);
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    saveEdit($form, $id, $token, [...validSubmissionAnswers(), 'nome' => 'Maria Souza Lima'])
        ->assertOk()
        ->assertJsonPath('data.answers.nome', 'Maria Souza Lima')
        ->assertJsonPath('data.edited_at', fn (?string $editedAt): bool => $editedAt !== null);

    $submission = Submission::query()->sole();

    expect($submission->data['nome'])->toBe('Maria Souza Lima')
        ->and($submission->revisions)->toHaveCount(1)
        ->and($submission->revisions->first()->data['nome'])->toBe('Maria Souza')
        ->and($form->fresh()->submissions_count)->toBe(1);

    Event::assertDispatched(SubmissionEdited::class);
});

it('atualiza o email de quem respondeu quando a edição o muda', function () {
    Event::fake([SubmissionEdited::class]);
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    saveEdit($form, $id, $token, [...validSubmissionAnswers(), 'email' => 'Nova@Example.com'])->assertOk();

    expect(Submission::query()->findOrFail($id)->respondent_email)->toBe('nova@example.com');
});

it('valida a edição com as mesmas regras e não altera nada se houver erro', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    saveEdit($form, $id, $token, [...validSubmissionAnswers(), 'email' => 'invalido'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Submission::query()->sole()->data['email'])->toBe('maria@example.com')
        ->and(Submission::query()->sole()->revisions)->toHaveCount(0);
});

it('valida a edição contra a versão original, mesmo depois de republicar', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    $schema = $form->schema;
    $schema['fields'][] = ['id' => 'cpf', 'type' => 'short_text', 'label' => 'CPF', 'help_text' => null, 'required' => true, 'placeholder' => null, 'rules' => []];
    $form->forceFill(['schema' => $schema, 'has_unpublished_changes' => true])->save();
    app(PublishForm::class)($form, $form->user);

    saveEdit($form, $id, $token, validSubmissionAnswers())
        ->assertOk()
        ->assertJsonPath('data.version', 1);
});

it('expira o link depois de 4 horas', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    $this->travel(4)->hours();
    $this->travel(1)->minutes();

    openForEdit($form, $id, $token)->assertGone()->assertExactJson(['message' => RespondentEditGuard::EXPIRED_MESSAGE]);
    saveEdit($form, $id, $token, validSubmissionAnswers())->assertGone();
});

it('bloqueia a edição quando o formulário é fechado', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    app(CloseForm::class)($form);

    saveEdit($form, $id, $token, validSubmissionAnswers())
        ->assertConflict()
        ->assertExactJson(['message' => RespondentEditGuard::FORM_CLOSED_MESSAGE]);
});

it('bloqueia a edição quando o dono desliga a opção', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    [$id, $token] = submitEditable($form);

    $form->forceFill(['settings' => new FormSettings(allowRespondentEdit: false)])->save();

    openForEdit($form, $id, $token)->assertConflict();
});

it('permite editar mesmo com o limite de respostas atingido', function () {
    $form = publishedForm(['allow_respondent_edit' => true, 'max_submissions' => 1]);
    [$id, $token] = submitEditable($form);

    saveEdit($form, $id, $token, [...validSubmissionAnswers(), 'nome' => 'Maria Lima'])->assertOk();
});
