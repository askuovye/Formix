<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $answers
 */
function submitAnswers(Form $form, array $answers = []): string
{
    return (string) test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [...validSubmissionAnswers(), ...$answers]])
        ->assertCreated()
        ->json('data.id');
}

it('lista só as respostas dos formulários do usuário, das mais novas para as mais antigas', function () {
    $form = publishedForm();
    $first = submitAnswers($form, ['nome' => 'Primeira Pessoa']);
    $this->travel(1)->seconds();
    $second = submitAnswers($form, ['nome' => 'Segunda Pessoa']);
    submitAnswers(publishedForm());

    $this->actingAs($form->user)
        ->getJson('/api/v1/me/submissions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second)
        ->assertJsonPath('data.1.id', $first)
        ->assertJsonPath('data.0.form', ['id' => $form->id, 'title' => $form->title])
        ->assertJsonPath('data.0.preview.0', ['label' => 'Nome completo', 'value' => 'Segunda Pessoa'])
        ->assertJsonPath('data.0.files_count', 0);
});

it('ordena das mais antigas para as mais novas quando pedido', function () {
    $form = publishedForm();
    $first = submitAnswers($form);
    $this->travel(1)->seconds();
    submitAnswers($form);

    $this->actingAs($form->user)->getJson('/api/v1/me/submissions?sort=oldest')->assertJsonPath('data.0.id', $first);
});

it('filtra por formulário do próprio usuário', function () {
    $form = publishedForm();
    $other = app(PublishForm::class)(
        Form::factory()->for($form->user)->create(['schema' => $form->schema]),
        $form->user,
    );
    submitAnswers($form);
    submitAnswers($other);

    $this->actingAs($form->user)
        ->getJson("/api/v1/me/submissions?form={$form->id}")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.form.id', $form->id);
});

it('busca nas respostas sem diferenciar maiúsculas nem acentos', function () {
    $form = publishedForm();
    $joao = submitAnswers($form, ['nome' => 'João Conceição']);
    submitAnswers($form, ['nome' => 'Maria Lima']);

    foreach (['joao', 'JOÃO', 'conceicao'] as $term) {
        $this->actingAs($form->user)
            ->getJson('/api/v1/me/submissions?q='.urlencode($term))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $joao);
    }

    // Curingas do LIKE digitados pelo usuário valem como texto.
    $this->getJson('/api/v1/me/submissions?q=%25')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/me/submissions?q=')->assertJsonCount(2, 'data');
});

it('a busca acompanha a edição da resposta', function () {
    $form = publishedForm();
    $id = submitAnswers($form, ['nome' => 'Nome Antigo']);

    $submission = Submission::query()->findOrFail($id);
    $submission->forceFill(['data' => [...$submission->data, 'nome' => 'Nome Novo']])->save();

    $this->actingAs($form->user)->getJson('/api/v1/me/submissions?q=antigo')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/me/submissions?q=novo')->assertJsonCount(1, 'data');
});

it('valida o tamanho da busca', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/me/submissions?q='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('recusa filtrar por formulário de outra pessoa', function () {
    $form = publishedForm();
    submitAnswers($form);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/me/submissions?form={$form->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['form']);
});

it('filtra por período, com data pura valendo o dia inteiro em Brasília', function () {
    $form = publishedForm();

    $this->travelTo('2026-10-10 02:30:00'); // 09/10 23:30 em Brasília
    submitAnswers($form);
    $this->travelTo('2026-10-10 15:00:00'); // 10/10 12:00 em Brasília
    $target = submitAnswers($form);
    $this->travelTo('2026-10-11 12:00:00');
    submitAnswers($form);

    $this->actingAs($form->user)
        ->getJson('/api/v1/me/submissions?from=2026-10-10&to=2026-10-10')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target);
});

it('valida o período', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/me/submissions?from=2026-10-10&to=2026-10-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('pagina por cursor e limita o tamanho da página', function () {
    $form = publishedForm();
    foreach (range(1, 3) as $i) {
        submitAnswers($form);
    }

    $page = $this->actingAs($form->user)->getJson('/api/v1/me/submissions?per_page=2')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/me/submissions?per_page=2&cursor='.$page->json('meta.next_cursor'))->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/me/submissions?per_page=500')->assertUnprocessable();
});

it('não lista respostas de formulário excluído', function () {
    $form = publishedForm();
    submitAnswers($form);
    $form->delete();

    $this->actingAs($form->user)->getJson('/api/v1/me/submissions')->assertJsonCount(0, 'data');
});

it('mostra o detalhe com respostas legíveis, anexos com link e histórico', function () {
    $form = publishedForm(['allow_respondent_edit' => true]);
    $receipt = $this->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [UploadedFile::fake()->createWithContent('Recibo.pdf', "%PDF-1.4\n%%EOF\n")]]],
        ['Accept' => 'application/json'],
    )->assertCreated()->json('data');

    $this->withHeaders(['X-Edit-Token' => explode('#token=', $receipt['edit_url'])[1]])
        ->putJson("/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}", ['answers' => [...validSubmissionAnswers(), 'nome' => 'Maria Lima']])
        ->assertOk();

    $this->actingAs($form->user)
        ->getJson("/api/v1/me/submissions/{$receipt['id']}")
        ->assertOk()
        ->assertJsonPath('data.answers.0', ['field_id' => 'nome', 'label' => 'Nome completo', 'value' => 'Maria Lima', 'multiline' => false, 'answered' => true])
        ->assertJsonPath('data.answers.3.value', 'Boa')
        ->assertJsonPath('data.files.0.field_id', 'comprovante')
        ->assertJsonPath('data.files.0.files.0.name', 'Recibo.pdf')
        ->assertJsonPath('data.files.0.files.0.url', fn (string $url): bool => str_contains($url, 'signature='))
        ->assertJsonCount(1, 'data.revisions')
        ->assertJsonPath('data.revisions.0.answers.0.value', 'Maria Souza');
});

it('responde 404 para resposta de outra pessoa', function (string $method) {
    $form = publishedForm();
    $id = submitAnswers($form);

    $this->actingAs(User::factory()->create())->json($method, "/api/v1/me/submissions/{$id}")->assertNotFound();

    expect(Submission::query()->count())->toBe(1);
})->with(['GET', 'DELETE']);

it('exige login no painel', function () {
    $this->getJson('/api/v1/me/submissions')->assertUnauthorized();
});

it('exclui a resposta, os anexos do disco e atualiza o contador', function () {
    $form = publishedForm(['max_submissions' => 1]);
    $id = (string) $this->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [UploadedFile::fake()->createWithContent('Recibo.pdf', "%PDF-1.4\n%%EOF\n")]]],
        ['Accept' => 'application/json'],
    )->assertCreated()->json('data.id');
    $path = Media::query()->sole()->getPathRelativeToRoot();

    $this->actingAs($form->user)->deleteJson("/api/v1/me/submissions/{$id}")->assertNoContent();

    expect(Submission::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and($form->fresh()->submissions_count)->toBe(0);
    Storage::disk('local')->assertMissing($path);

    // Com o contador de volta a 0, o formulário com limite 1 aceita respostas de novo.
    submitAnswers($form);
});
