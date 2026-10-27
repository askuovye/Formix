<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Models\Export;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\FormSchema\FormSchemaNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $answers
 */
function answerResults(Form $form, array $answers = []): string
{
    return (string) test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [...validSubmissionAnswers(), ...$answers]])
        ->assertCreated()
        ->json('data.id');
}

/**
 * Formulário com os tipos numéricos e de escolha múltipla.
 */
function numericForm(): Form
{
    $form = Form::factory()->create(['schema' => app(FormSchemaNormalizer::class)->normalize(['fields' => [
        ['id' => 'nota', 'type' => 'rating', 'label' => 'Nota', 'rating' => ['max' => 5]],
        ['id' => 'recomenda', 'type' => 'scale', 'label' => 'Recomendaria?', 'scale' => ['min' => 0, 'max' => 3]],
        ['id' => 'idade', 'type' => 'number', 'label' => 'Idade'],
        ['id' => 'quando', 'type' => 'date', 'label' => 'Quando'],
        ['id' => 'canais', 'type' => 'multiple_choice', 'label' => 'Canais',
            'options' => [['value' => 'busca', 'label' => 'Busca'], ['value' => 'amigos', 'label' => 'Amigos']]],
    ]])]);

    return app(PublishForm::class)($form, $form->user);
}

/**
 * @return array<string, mixed>
 */
function resultField(array $results, string $id): array
{
    return collect($results['fields'])->firstWhere('id', $id);
}

it('agrega totais, escolhas, textos recentes, anexos e a linha do tempo', function () {
    $form = publishedForm();
    $this->travelTo(now()->subDays(10));
    answerResults($form, ['nota' => 'ruim']);
    $this->travelBack();

    foreach (['Ana Paula', 'Bruno Lima', 'Carla Dias', 'Diego Reis', 'Elisa Melo', 'Fabio Cruz'] as $name) {
        answerResults($form, ['nome' => $name, 'email' => strtolower(explode(' ', $name)[0]).'@example.com']);
        $this->travel(1)->seconds();
    }

    $this->post("/api/v1/forms/{$form->slug}/submissions", [
        'answers' => json_encode(validSubmissionAnswers()),
        'files' => ['comprovante' => [UploadedFile::fake()->createWithContent('rg.pdf', "%PDF-1.4\n%%EOF")]],
    ], ['Accept' => 'application/json'])->assertCreated();

    $results = $this->actingAs($form->user)->getJson("/api/v1/me/forms/{$form->id}/results")->assertOk()->json('data');

    expect($results['total'])->toBe(8)
        ->and($results['last_7_days'])->toBe(7)
        ->and($results['edited'])->toBe(0)
        ->and($results['timeline']['unit'])->toBe('day')
        ->and($results['timeline']['points'])->toHaveCount(30)
        ->and(collect($results['timeline']['points'])->sum('count'))->toBe(8)
        ->and(end($results['timeline']['points'])['count'])->toBe(7);

    $nota = resultField($results, 'nota');
    expect($nota['answered'])->toBe(8)
        ->and($nota['options'])->toBe([
            ['value' => 'boa', 'label' => 'Boa', 'count' => 7],
            ['value' => 'ruim', 'label' => 'Ruim', 'count' => 1],
        ]);

    // Só as 5 mais recentes, da mais nova para a mais antiga.
    $nome = resultField($results, 'nome');
    expect($nome['answered'])->toBe(8)
        ->and(array_column($nome['latest'], 'value'))->toBe(['Maria Souza', 'Fabio Cruz', 'Elisa Melo', 'Diego Reis', 'Carla Dias']);

    expect(resultField($results, 'comprovante')['answered'])->toBe(1)
        ->and(resultField($results, 'relato')['answered'])->toBe(8);
});

it('calcula média e distribuição de estrelas e escala, número e datas', function () {
    $form = numericForm();

    foreach ([[5, 3, '30', '2026-01-10', ['busca']], [3, 0, '42.5', '2026-03-02', ['busca', 'amigos']], [4, 3, null, null, []]] as [$nota, $recomenda, $idade, $quando, $canais]) {
        $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [
            'nota' => $nota, 'recomenda' => $recomenda, 'idade' => $idade, 'quando' => $quando, 'canais' => $canais,
        ]])->assertCreated();
    }

    $results = $this->actingAs($form->user)->getJson("/api/v1/me/forms/{$form->id}/results")->assertOk()->json('data');

    expect(resultField($results, 'nota'))->toMatchArray([
        'answered' => 3,
        'average' => 4,
        'distribution' => [['value' => 1, 'count' => 0], ['value' => 2, 'count' => 0], ['value' => 3, 'count' => 1], ['value' => 4, 'count' => 1], ['value' => 5, 'count' => 1]],
    ]);
    expect(resultField($results, 'recomenda'))->toMatchArray([
        'average' => 2,
        'distribution' => [['value' => 0, 'count' => 1], ['value' => 1, 'count' => 0], ['value' => 2, 'count' => 0], ['value' => 3, 'count' => 2]],
    ]);
    expect(resultField($results, 'idade'))->toMatchArray(['answered' => 2, 'min' => 30, 'max' => 42.5, 'average' => 36.25]);
    expect(resultField($results, 'quando'))->toMatchArray(['answered' => 2, 'earliest' => '2026-01-10', 'latest' => '2026-03-02']);
    expect(resultField($results, 'canais')['options'])->toBe([
        ['value' => 'busca', 'label' => 'Busca', 'count' => 2],
        ['value' => 'amigos', 'label' => 'Amigos', 'count' => 1],
    ]);
});

it('aplica os mesmos filtros da lista: período, busca e pessoa', function () {
    $form = publishedForm();
    $this->travelTo('2026-01-05 15:00:00');
    answerResults($form, ['nome' => 'Antiga Pessoa']);
    $this->travelTo('2026-10-20 15:00:00');
    answerResults($form, ['nome' => 'João Conceição', 'email' => 'Joao@Example.com']);
    answerResults($form, ['nome' => 'Maria Lima']);

    $this->actingAs($form->user);
    expect($this->getJson("/api/v1/me/forms/{$form->id}/results?from=2026-10-01")->json('data.total'))->toBe(2)
        ->and($this->getJson("/api/v1/me/forms/{$form->id}/results?q=joao")->json('data.total'))->toBe(1)
        ->and($this->getJson("/api/v1/me/forms/{$form->id}/results?email=JOAO@example.com")->json('data.total'))->toBe(1);

    // Período filtrado: a linha do tempo começa nele; acima de 90 dias, por semana (segunda-feira).
    $daily = $this->getJson("/api/v1/me/forms/{$form->id}/results?from=2026-10-01&to=2026-10-20")->json('data.timeline');
    expect($daily['unit'])->toBe('day')
        ->and($daily['points'][0]['date'])->toBe('2026-10-01')
        ->and($daily['points'])->toHaveCount(20);

    $weekly = $this->getJson("/api/v1/me/forms/{$form->id}/results?from=2026-01-01")->json('data.timeline');
    expect($weekly['unit'])->toBe('week')
        ->and($weekly['points'][0]['date'])->toBe('2025-12-29')
        ->and(collect($weekly['points'])->sum('count'))->toBe(3);
});

it('não mostra resultados de formulário de outra pessoa nem sem login', function () {
    $form = publishedForm();

    $this->actingAs(User::factory()->create())->getJson("/api/v1/me/forms/{$form->id}/results")->assertNotFound();
    auth()->forgetGuards();
    $this->getJson("/api/v1/me/forms/{$form->id}/results")->assertUnauthorized();
});

it('rascunho nunca publicado tem resultados vazios', function () {
    $form = Form::factory()->create();

    $this->actingAs($form->user)
        ->getJson("/api/v1/me/forms/{$form->id}/results")
        ->assertOk()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.fields', []);
});

it('guarda o email de quem respondeu e filtra todas as respostas da pessoa', function () {
    $form = publishedForm();
    $other = app(PublishForm::class)(Form::factory()->for($form->user)->create(['schema' => $form->schema]), $form->user);
    $first = answerResults($form, ['email' => 'Ana@Example.com']);
    $second = answerResults($other, ['email' => 'ana@example.com']);
    answerResults($form, ['email' => 'bia@example.com']);

    expect(Submission::query()->findOrFail($first)->respondent_email)->toBe('ana@example.com');

    $this->actingAs($form->user)
        ->getJson("/api/v1/me/submissions/{$first}")
        ->assertJsonPath('data.respondent_email', 'ana@example.com');

    $ids = $this->getJson('/api/v1/me/submissions?email=ANA@example.com')->assertOk()->json('data.*.id');
    expect($ids)->toEqualCanonicalizing([$first, $second]);

    $this->getJson('/api/v1/me/submissions?email=nao-e-email')->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('a exportação leva o filtro da pessoa', function () {
    $form = publishedForm();
    answerResults($form, ['email' => 'ana@example.com']);
    answerResults($form, ['email' => 'bia@example.com']);

    $id = $this->actingAs($form->user)
        ->postJson("/api/v1/me/forms/{$form->id}/exports", ['email' => 'ana@example.com'])
        ->assertAccepted()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->json('data.id');

    expect(Export::query()->findOrFail($id)->rows_count)->toBe(1);
});

it('mostra o tempo médio e os aparelhos de quem respondeu', function () {
    $form = publishedForm();
    $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1';
    $tablet = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

    foreach ([[$phone, 60], [$phone, 90], [$tablet, null]] as [$agent, $seconds]) {
        $this->withHeaders(['User-Agent' => $agent])
            ->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers(), 'duration_seconds' => $seconds])
            ->assertCreated();
    }

    $results = $this->actingAs($form->user)->getJson("/api/v1/me/forms/{$form->id}/results")->assertOk()->json('data');

    // Sem tempo medido, a resposta fica fora da média.
    expect($results['average_duration_seconds'])->toBe(75)
        ->and($results['devices'])->toBe([
            ['device' => 'desktop', 'count' => 0],
            ['device' => 'mobile', 'count' => 2],
            ['device' => 'tablet', 'count' => 1],
        ])
        ->and($results['previous'])->toBeNull();
});

it('compara com o período anterior de mesmo tamanho', function () {
    $form = publishedForm();
    $send = function (string $at, int $seconds) use ($form): void {
        $this->travelTo($at);
        $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers(), 'duration_seconds' => $seconds])
            ->assertCreated();
    };

    // Período de 10 dias (11 a 20): o anterior vai de 1 a 10.
    $send('2026-10-05 12:00:00', 100);
    $send('2026-10-06 12:00:00', 200);
    $send('2026-09-28 12:00:00', 999);
    $send('2026-10-15 12:00:00', 50);
    $this->travelTo('2026-10-25 12:00:00');

    $results = $this->actingAs($form->user)
        ->getJson("/api/v1/me/forms/{$form->id}/results?from=2026-10-11&to=2026-10-20")
        ->assertOk()
        ->json('data');

    expect($results['total'])->toBe(1)
        ->and($results['average_duration_seconds'])->toBe(50)
        ->and($results['previous'])->toBe(['total' => 2, 'average_duration_seconds' => 150]);
});
