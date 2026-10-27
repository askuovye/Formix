<?php

declare(strict_types=1);

use App\Models\Export;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\Submissions\SubmissionsPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $answers
 */
function answerForPdf(Form $form, array $answers = []): Submission
{
    $id = test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [...validSubmissionAnswers(), ...$answers]])
        ->assertCreated()
        ->json('data.id');

    return Submission::query()->with('media')->findOrFail($id);
}

it('baixa o PDF de uma resposta como anexo', function () {
    $form = publishedForm();
    $submission = answerForPdf($form);

    $response = $this->actingAs($form->user)->get("/api/v1/me/submissions/{$submission->id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain("{$submission->id}.pdf")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getContent())->toStartWith('%PDF-');
});

it('não entrega o PDF de resposta de outra pessoa nem sem login', function () {
    $submission = answerForPdf(publishedForm());

    $this->actingAs(User::factory()->create())->get("/api/v1/me/submissions/{$submission->id}/pdf")->assertNotFound();
    auth()->forgetGuards();
    $this->getJson("/api/v1/me/submissions/{$submission->id}/pdf")->assertUnauthorized();
});

it('monta o PDF com respostas legíveis, anexos pelo nome e conteúdo escapado', function () {
    $form = publishedForm();
    $this->post("/api/v1/forms/{$form->slug}/submissions", [
        'answers' => json_encode([...validSubmissionAnswers(), 'nome' => '<script>alert(1)</script> Ana']),
        'files' => ['comprovante' => [UploadedFile::fake()->createWithContent('rg.pdf', "%PDF-1.4\n%%EOF")]],
    ], ['Accept' => 'application/json'])->assertCreated();
    $submission = Submission::query()->with('media')->latest('id')->firstOrFail();

    $html = app(SubmissionsPdfRenderer::class)->html($form, [$submission]);

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt; Ana')
        ->not->toContain('<script>alert(1)')
        ->toContain('Nome completo')
        ->toContain('Boa')
        ->toContain('rg.pdf')
        ->toContain('Recebida em');
});

it('exporta todas as respostas filtradas em PDF', function () {
    $form = publishedForm();
    answerForPdf($form, ['nome' => 'João Conceição']);
    answerForPdf($form, ['nome' => 'Maria Lima']);

    $id = $this->actingAs($form->user)
        ->postJson("/api/v1/me/forms/{$form->id}/exports", ['format' => 'pdf'])
        ->assertAccepted()
        ->assertJsonPath('data.format', 'pdf')
        ->json('data.id');

    $export = Export::query()->findOrFail($id);
    expect($export->rows_count)->toBe(2)
        ->and($export->path)->toEndWith('.pdf')
        ->and(Storage::disk('local')->get((string) $export->path))->toStartWith('%PDF-');

    $download = $this->get((string) $this->getJson("/api/v1/me/exports/{$id}")->json('data.download_url'))->assertOk();
    expect($download->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($download->headers->get('Content-Disposition'))->toContain('.pdf');
});

it('recusa PDF acima do teto de respostas e sugere filtrar ou usar CSV', function () {
    config(['submissions.pdf_export_max_rows' => 1]);
    $form = publishedForm();
    answerForPdf($form);
    answerForPdf($form);

    $this->actingAs($form->user)
        ->postJson("/api/v1/me/forms/{$form->id}/exports", ['format' => 'pdf'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');

    // A busca reduz para dentro do teto; o CSV não tem teto.
    $this->postJson("/api/v1/me/forms/{$form->id}/exports", ['format' => 'pdf', 'q' => 'inexistente'])->assertAccepted();
    $this->postJson("/api/v1/me/forms/{$form->id}/exports", ['format' => 'csv'])->assertAccepted();
});

it('valida o formato', function () {
    $form = publishedForm();

    $this->actingAs($form->user)
        ->postJson("/api/v1/me/forms/{$form->id}/exports", ['format' => 'docx'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

it('expõe o nome do arquivo ao painel por CORS', function () {
    $form = publishedForm();
    $submission = answerForPdf($form);

    $response = $this->actingAs($form->user)
        ->withHeader('Origin', (string) config('app.frontend_url'))
        ->get("/api/v1/me/submissions/{$submission->id}/pdf")
        ->assertOk();

    expect((string) $response->headers->get('Access-Control-Expose-Headers'))->toContain('Content-Disposition');
});
