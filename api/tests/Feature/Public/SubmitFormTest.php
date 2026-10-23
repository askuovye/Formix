<?php

declare(strict_types=1);

use App\Actions\Forms\CloseForm;
use App\Actions\Submissions\SubmitFormResponse;
use App\Enums\FormAvailability;
use App\Events\SubmissionReceived;
use App\Http\Requests\Submissions\StoreSubmissionRequest;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\IpHasher;
use App\Services\Submissions\CaptchaVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function submitTo(Form $form, array $body, array $headers = []): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['User-Agent' => 'NavegadorDeTeste/1.0', ...$headers])
        ->postJson("/api/v1/forms/{$form->slug}/submissions", $body);
}

it('grava a resposta normalizada e responde 201 só com o recibo', function () {
    $form = publishedForm();

    $response = submitTo($form, ['answers' => [...validSubmissionAnswers(), 'nome' => '  Maria   Souza ']])
        ->assertCreated()
        ->assertJsonPath('data.success_message', SubmitFormResponse::DEFAULT_SUCCESS_MESSAGE)
        ->assertJsonPath('data.edit_url', null)
        ->assertJsonPath('data.editable_until', null);

    expect(array_keys($response->json('data')))->toBe(['id', 'success_message', 'edit_url', 'editable_until']);

    $submission = Submission::query()->sole();

    expect($response->json('data.id'))->toBe($submission->id)
        ->and($submission->form_id)->toBe($form->id)
        ->and($submission->schema_version)->toBe(1)
        ->and($submission->data['nome'])->toBe('Maria Souza')
        ->and($submission->data)->not->toHaveKey('comprovante')
        ->and($submission->ip_hash)->toBe(app(IpHasher::class)->hash('198.51.100.7'))
        ->and($submission->user_agent)->toBe('NavegadorDeTeste/1.0')
        ->and($form->fresh()->submissions_count)->toBe(1);
});

it('nunca grava o IP puro', function () {
    submitTo(publishedForm(), ['answers' => validSubmissionAnswers()])->assertCreated();

    expect(json_encode(Submission::query()->sole()->getAttributes()))->not->toContain('198.51.100.7');
});

it('usa a mensagem de sucesso do dono', function () {
    submitTo(publishedForm(['success_message' => 'Recebemos, valeu!']), ['answers' => validSubmissionAnswers()])
        ->assertCreated()
        ->assertJsonPath('data.success_message', 'Recebemos, valeu!');
});

it('valida contra a versão publicada, não o rascunho', function () {
    $form = publishedForm();
    $draft = $form->schema;
    $draft['fields'][] = ['id' => 'cpf', 'type' => 'short_text', 'label' => 'CPF', 'help_text' => null, 'required' => true, 'placeholder' => null, 'rules' => []];
    $form->forceFill(['schema' => $draft])->save();

    submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();
});

it('devolve erros por id de campo e não grava nada', function () {
    $form = publishedForm();

    submitTo($form, ['answers' => [...validSubmissionAnswers(), 'nome' => '', 'email' => 'invalido', 'nota' => 'otima']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nome', 'email', 'nota']);

    expect(Submission::query()->count())->toBe(0)
        ->and($form->fresh()->submissions_count)->toBe(0);
});

it('exige o objeto de respostas', function () {
    submitTo(publishedForm(), [])->assertUnprocessable()->assertJsonValidationErrors(['answers']);
});

it('ignora respostas para campos que não existem', function () {
    submitTo(publishedForm(), ['answers' => [...validSubmissionAnswers(), 'is_admin' => true]])->assertCreated();

    expect(Submission::query()->sole()->data)->not->toHaveKey('is_admin');
});

it('recusa respostas quando o formulário não aceita', function (array $settings, ?Closure $after, FormAvailability $expected) {
    $form = publishedForm($settings);
    $after?->__invoke($form);

    submitTo($form, ['answers' => validSubmissionAnswers()])
        ->assertConflict()
        ->assertExactJson(['message' => $expected->message()]);

    expect(Submission::query()->count())->toBe(0);
})->with([
    'fechado' => [[], fn (Form $form) => app(CloseForm::class)($form), FormAvailability::Closed],
    'ainda não abriu' => [['opens_at' => now()->addDay()->toIso8601String()], null, FormAvailability::NotOpenYet],
    'prazo encerrado' => [['closes_at' => now()->subMinute()->toIso8601String()], null, FormAvailability::Ended],
]);

it('responde 404 para rascunho nunca publicado', function () {
    $form = Form::factory()->withFields()->create();

    submitTo($form, ['answers' => validSubmissionAnswers()])->assertNotFound();
});

it('respeita o limite exato de respostas', function () {
    $form = publishedForm(['max_submissions' => 2]);

    submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();
    submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();
    submitTo($form, ['answers' => validSubmissionAnswers()])
        ->assertConflict()
        ->assertExactJson(['message' => FormAvailability::LimitReached->message()]);

    expect(Submission::query()->count())->toBe(2)
        ->and($form->fresh()->submissions_count)->toBe(2);
});

it('finge sucesso para bots que preenchem o honeypot, sem gravar', function () {
    $form = publishedForm();

    $human = submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();
    $bot = submitTo($form, ['answers' => validSubmissionAnswers(), StoreSubmissionRequest::HONEYPOT_FIELD => 'http://spam.test'])->assertCreated();

    expect(array_keys($bot->json('data')))->toBe(array_keys($human->json('data')))
        ->and($bot->json('data.id'))->toHaveLength(26)
        ->and(Submission::query()->count())->toBe(1);
});

it('finge sucesso para bots também quando a edição está ativa', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);

    submitTo($form, ['answers' => [], StoreSubmissionRequest::HONEYPOT_FIELD => 'x'])
        ->assertCreated()
        ->assertJsonPath('data.edit_url', fn (?string $url): bool => $url !== null && str_contains($url, '#token='));

    expect(Submission::query()->count())->toBe(0);
});

it('não duplica a resposta com a mesma Idempotency-Key', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    $headers = [StoreSubmissionRequest::IDEMPOTENCY_HEADER => 'envio-0001-abc'];

    $first = submitTo($form, ['answers' => validSubmissionAnswers()], $headers)->assertCreated();
    $second = submitTo($form, ['answers' => validSubmissionAnswers()], $headers)->assertCreated();

    expect($second->json('data'))->toBe($first->json('data'))
        ->and(Submission::query()->count())->toBe(1)
        ->and($form->fresh()->submissions_count)->toBe(1);
});

it('repete o recibo mesmo se o formulário fechou depois do primeiro envio', function () {
    $form = publishedForm();
    $headers = [StoreSubmissionRequest::IDEMPOTENCY_HEADER => 'envio-0002-abc'];
    $first = submitTo($form, ['answers' => validSubmissionAnswers()], $headers)->assertCreated();

    app(CloseForm::class)($form);

    submitTo($form, ['answers' => validSubmissionAnswers()], $headers)
        ->assertCreated()
        ->assertJsonPath('data.id', $first->json('data.id'));
});

it('repete o envio sem duplicar mesmo se o cache foi perdido', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    $headers = [StoreSubmissionRequest::IDEMPOTENCY_HEADER => 'envio-0003-abc'];
    $first = submitTo($form, ['answers' => validSubmissionAnswers()], $headers)->assertCreated();

    Cache::flush();

    submitTo($form, ['answers' => validSubmissionAnswers()], $headers)
        ->assertCreated()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.edit_url', null);

    expect(Submission::query()->count())->toBe(1);
});

it('cria respostas distintas para chaves distintas', function () {
    $form = publishedForm();

    submitTo($form, ['answers' => validSubmissionAnswers()], [StoreSubmissionRequest::IDEMPOTENCY_HEADER => 'envio-aaaa-1'])->assertCreated();
    submitTo($form, ['answers' => validSubmissionAnswers()], [StoreSubmissionRequest::IDEMPOTENCY_HEADER => 'envio-aaaa-2'])->assertCreated();

    expect(Submission::query()->count())->toBe(2);
});

it('recusa Idempotency-Key fora do formato', function (string $key) {
    submitTo(publishedForm(), ['answers' => validSubmissionAnswers()], [StoreSubmissionRequest::IDEMPOTENCY_HEADER => $key])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);
})->with(['curta' => 'abc', 'com espaço' => 'chave com espaco', 'longa' => str_repeat('a', 129)]);

it('limita envios repetidos do mesmo IP no mesmo formulário', function () {
    $form = publishedForm();

    for ($i = 0; $i < 10; $i++) {
        submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();
    }

    submitTo($form, ['answers' => validSubmissionAnswers()])->assertTooManyRequests()->assertHeader('Retry-After');
});

it('recusa corpo grande demais antes de processar', function () {
    submitTo(publishedForm(), ['answers' => ['relato' => str_repeat('a', 1100 * 1024)]])
        ->assertStatus(413)
        ->assertExactJson(['message' => 'O conteúdo enviado é grande demais.']);
});

it('avisa os interessados depois de gravar, levando o token de edição', function () {
    Event::fake([SubmissionReceived::class]);
    $form = publishedForm(['allow_respondent_edit' => true]);

    submitTo($form, ['answers' => validSubmissionAnswers()])->assertCreated();

    Event::assertDispatched(SubmissionReceived::class, fn (SubmissionReceived $event): bool => $event->submission->form_id === $form->id
        && $event->editToken !== null);
});

it('grava texto com SQL injection literalmente, sem afetar o banco', function (string $payload) {
    $form = publishedForm();

    submitTo($form, ['answers' => [...validSubmissionAnswers(), 'relato' => "Comentário com {$payload} no meio"]])->assertCreated();

    expect(Submission::query()->sole()->data['relato'])->toBe("Comentário com {$payload} no meio")
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
})->with([
    "'; DROP TABLE users; --",
    '" OR "1"="1',
    "1' UNION SELECT password FROM users --",
    "\\'; DELETE FROM submissions; --",
]);

it('não aceita SQL injection no endereço do formulário', function () {
    publishedForm();

    $this->getJson("/api/v1/forms/x' OR '1'='1")->assertNotFound();
    $this->postJson("/api/v1/forms/x' OR '1'='1/submissions", ['answers' => []])->assertNotFound();
});

it('passa pelo verificador de CAPTCHA quando ele estiver ativo', function () {
    app()->bind(CaptchaVerifier::class, fn () => new class implements CaptchaVerifier
    {
        public function verify(Request $request): bool
        {
            return false;
        }
    });

    submitTo(publishedForm(), ['answers' => validSubmissionAnswers()])
        ->assertUnprocessable()
        ->assertJsonPath('errors.captcha.0', StoreSubmissionRequest::CAPTCHA_FAILED_MESSAGE);

    expect(Submission::query()->count())->toBe(0);
});
