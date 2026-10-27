<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Actions\Submissions\ListOwnerSubmissions;
use App\Actions\Submissions\RequestSubmissionsExport;
use App\Enums\ExportStatus;
use App\Jobs\GenerateSubmissionsExport;
use App\Jobs\PurgeExpiredExports;
use App\Models\Export;
use App\Models\Form;
use App\Models\User;
use App\Services\Submissions\SubmissionsCsvWriter;
use App\Services\Submissions\SubmissionsPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $answers
 */
function answerForm(Form $form, array $answers = []): void
{
    test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [...validSubmissionAnswers(), ...$answers]])->assertCreated();
}

function requestExport(Form $form, array $body = []): TestResponse
{
    return test()->actingAs($form->user)->postJson("/api/v1/me/forms/{$form->id}/exports", $body);
}

/**
 * @return list<list<string>>
 */
function csvRows(Export $export): array
{
    $content = (string) Storage::disk('local')->get((string) $export->path);

    expect($content)->toStartWith("\xEF\xBB\xBF");

    // fgetcsv, não explode("\n"): campos de texto longo têm quebras de linha entre aspas.
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($content, 3));
    rewind($stream);

    $rows = [];

    while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
        $rows[] = $row;
    }

    fclose($stream);

    return $rows;
}

it('aceita o pedido e gera o CSV na fila de exportações', function () {
    Queue::fake();
    $form = publishedForm();

    requestExport($form)
        ->assertAccepted()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.download_url', null);

    Queue::assertPushedOn('exports', GenerateSubmissionsExport::class, fn (GenerateSubmissionsExport $job): bool => $job->connection === config('queue.long_connection'));
});

it('usa a conexão de jobs longos por padrão em produção', function () {
    expect(require base_path('config/queue.php'))->toHaveKey('long_connection')
        ->and(config('queue.connections.database-long.retry_after'))->toBeGreaterThan(config('queue.connections.database.retry_after'));
});

it('gera o CSV com cabeçalho, respostas legíveis e formato do Excel em português', function () {
    $form = publishedForm();
    answerForm($form, ['nome' => 'Maria Souza', 'relato' => "Linha 1\nLinha 2"]);

    $export = Export::query()->findOrFail(requestExport($form)->json('data.id'));
    $rows = csvRows($export);

    expect($export->status)->toBe(ExportStatus::Ready)
        ->and($export->rows_count)->toBe(1)
        ->and($rows[0])->toBe(['ID', 'Enviada em', 'Editada em', 'Versão', 'Nome completo', 'Email', 'Conte sua experiência', 'Nota', 'Comprovante'])
        ->and($rows[1][4])->toBe('Maria Souza')
        ->and($rows[1][6])->toBe("Linha 1\nLinha 2")
        ->and($rows[1][7])->toBe('Boa')
        ->and($rows[1][8])->toBe('');
});

it('neutraliza fórmulas digitadas pelo respondente (CSV injection)', function (string $payload) {
    $form = publishedForm();
    answerForm($form, ['relato' => $payload.' resto do texto']);

    $export = Export::query()->findOrFail(requestExport($form)->json('data.id'));

    expect(csvRows($export)[1][6])->toBe("'".$payload.' resto do texto');
})->with([
    '=HYPERLINK("http://golpe.test","Clique")',
    '+SOMA(1;2)',
    '-2+3',
    '@SUM(A1)',
]);

it('lista os nomes dos anexos na coluna do campo de arquivo', function () {
    $form = publishedForm();
    $this->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [
            UploadedFile::fake()->createWithContent('A.pdf', "%PDF-1.4\n%%EOF\n"),
            UploadedFile::fake()->createWithContent('B.pdf', "%PDF-1.4\n%%EOF\n"),
        ]]],
        ['Accept' => 'application/json'],
    )->assertCreated();

    $export = Export::query()->findOrFail(requestExport($form)->json('data.id'));

    expect(csvRows($export)[1][8])->toBe('A.pdf | B.pdf');
});

it('mantém colunas de campos que só existiram em versões antigas', function () {
    $form = publishedForm();
    answerForm($form);

    $schema = $form->schema;
    $schema['fields'] = array_values(array_filter($schema['fields'], fn (array $field): bool => $field['id'] !== 'relato'));
    $form->forceFill(['schema' => $schema, 'has_unpublished_changes' => true])->save();
    app(PublishForm::class)($form, $form->user);
    answerForm($form);

    $rows = csvRows(Export::query()->findOrFail(requestExport($form)->json('data.id')));

    expect($rows[0])->toBe(['ID', 'Enviada em', 'Editada em', 'Versão', 'Nome completo', 'Email', 'Nota', 'Comprovante', 'Conte sua experiência'])
        ->and($rows[1][8])->toBe('Fui muito bem atendida pela equipe.')
        ->and($rows[2][8])->toBe('');
});

it('respeita o período pedido', function () {
    $form = publishedForm();
    $this->travelTo('2026-10-01 15:00:00');
    answerForm($form);
    $this->travelTo('2026-10-20 15:00:00');
    answerForm($form);

    $export = Export::query()->findOrFail(requestExport($form, ['from' => '2026-10-15', 'to' => '2026-10-31'])->json('data.id'));

    expect($export->rows_count)->toBe(1);
});

it('respeita a busca pedida, igual à lista do painel', function () {
    $form = publishedForm();
    answerForm($form, ['nome' => 'João Conceição']);
    answerForm($form, ['nome' => 'Maria Lima']);

    $response = requestExport($form, ['q' => 'joao'])->assertAccepted()->assertJsonPath('data.search', 'joao');
    $export = Export::query()->findOrFail($response->json('data.id'));

    expect($export->rows_count)->toBe(1)
        ->and(implode(';', csvRows($export)[1]))->toContain('João Conceição');
});

it('valida o tamanho da busca da exportação', function () {
    requestExport(publishedForm(), ['q' => str_repeat('a', 101)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('entrega o CSV pelo link assinado enquanto não expira', function () {
    $form = publishedForm();
    answerForm($form);
    $id = requestExport($form)->json('data.id');

    $url = $this->getJson("/api/v1/me/exports/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->json('data.download_url');

    $response = $this->get($url)->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toStartWith("\xEF\xBB\xBF");

    $this->get(preg_replace('/signature=\w+/', 'signature=x', $url))->assertForbidden();
});

it('não mostra exportação de outra pessoa', function () {
    $form = publishedForm();
    $id = requestExport($form)->json('data.id');

    $this->actingAs(User::factory()->create())->getJson("/api/v1/me/exports/{$id}")->assertNotFound();
});

it('não exporta formulário de outra pessoa', function () {
    $form = publishedForm();

    $this->actingAs(User::factory()->create())->postJson("/api/v1/me/forms/{$form->id}/exports")->assertNotFound();
});

it('limita exportações simultâneas', function () {
    Queue::fake();
    $form = publishedForm();

    foreach (range(1, RequestSubmissionsExport::MAX_ACTIVE_EXPORTS) as $i) {
        requestExport($form)->assertAccepted();
    }

    requestExport($form)->assertConflict()->assertExactJson(['message' => RequestSubmissionsExport::TOO_MANY_MESSAGE]);
});

it('apaga arquivo e registro quando a exportação vence', function () {
    $form = publishedForm();
    answerForm($form);
    $export = Export::query()->findOrFail(requestExport($form)->json('data.id'));
    Storage::disk('local')->assertExists((string) $export->path);

    $this->travel((int) config('submissions.export_ttl_hours') + 1)->hours();
    (new PurgeExpiredExports)->handle();

    Storage::disk('local')->assertMissing((string) $export->path);
    expect(Export::query()->count())->toBe(0);
});

it('marca como falha a exportação de formulário excluído no meio do caminho', function () {
    Queue::fake();
    $form = publishedForm();
    $id = requestExport($form)->json('data.id');
    $form->delete();

    (new GenerateSubmissionsExport($id))->handle(app(ListOwnerSubmissions::class), app(SubmissionsCsvWriter::class), app(SubmissionsPdfRenderer::class));

    expect(Export::query()->findOrFail($id)->status)->toBe(ExportStatus::Failed);
});
