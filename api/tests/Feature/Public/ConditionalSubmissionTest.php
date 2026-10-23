<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Data\FormSettings;
use App\Mail\NewSubmissionMail;
use App\Models\Form;
use App\Models\Submission;
use App\Services\FormSchema\FormSchemaNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/**
 * Formulário da referência: nota em estrelas; se a nota for ≤ 2, pede motivo
 * (obrigatório) e um print opcional; depois uma etapa com a escala de recomendação.
 *
 * @param  array<string, mixed>  $settings
 */
function satisfactionForm(array $settings = []): Form
{
    $when = ['field' => 'nota', 'operator' => 'lte', 'value' => 2];

    $form = Form::factory()->create([
        'schema' => app(FormSchemaNormalizer::class)->normalize(['fields' => [
            ['id' => 'titulo', 'kind' => 'block', 'type' => 'section_title', 'text' => 'Conte pra gente'],
            ['id' => 'nota', 'type' => 'rating', 'label' => 'Como você avalia o atendimento?', 'required' => true, 'rating' => ['max' => 5]],
            ['id' => 'motivo', 'type' => 'long_text', 'label' => 'O que podemos melhorar?', 'required' => true, 'visibility' => ['action' => 'show', 'when' => $when]],
            ['id' => 'print', 'type' => 'file', 'label' => 'Print do problema', 'required' => true, 'file' => ['accept' => ['image', 'pdf']], 'visibility' => ['action' => 'show', 'when' => $when]],
            ['id' => 'etapa_2', 'kind' => 'block', 'type' => 'page_break'],
            ['id' => 'recomenda', 'type' => 'scale', 'label' => 'Recomendaria?', 'required' => true, 'scale' => ['min' => 0, 'max' => 10]],
        ]]),
        'settings' => FormSettings::fromArray($settings),
    ]);

    return app(PublishForm::class)($form, $form->user);
}

function pdfUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('print.pdf', "%PDF-1.4\n%%EOF\n");
}

/**
 * @param  array<string, mixed>  $answers
 * @param  list<UploadedFile>  $printFiles
 */
function submitSatisfaction(Form $form, array $answers, array $printFiles = []): TestResponse
{
    return test()->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode($answers), 'files' => $printFiles === [] ? [] : ['print' => $printFiles]],
        ['Accept' => 'application/json'],
    );
}

it('não exige campo oculto e descarta o valor enviado para ele', function () {
    $form = satisfactionForm();

    submitSatisfaction($form, ['nota' => 5, 'motivo' => 'texto que o respondente não deveria ver', 'recomenda' => 9], [pdfUpload()])
        ->assertCreated();

    $submission = Submission::query()->sole();

    expect($submission->data)->toBe(['nota' => 5, 'motivo' => null, 'recomenda' => 9])
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('exige e valida o campo quando a condição o mostra', function () {
    $form = satisfactionForm();

    submitSatisfaction($form, ['nota' => 1, 'recomenda' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motivo', 'print']);
});

it('grava o campo e o arquivo quando estão visíveis', function () {
    $form = satisfactionForm();

    submitSatisfaction($form, ['nota' => 2, 'motivo' => 'Demorou muito para responder.', 'recomenda' => 4], [pdfUpload()])
        ->assertCreated();

    expect(Submission::query()->sole()->data['motivo'])->toBe('Demorou muito para responder.')
        ->and(Media::query()->sole()->getCustomProperty('field_id'))->toBe('print');
});

it('valida estrelas e escala dentro do intervalo', function (array $answers, string $errorKey) {
    submitSatisfaction(satisfactionForm(), $answers)->assertUnprocessable()->assertJsonValidationErrors([$errorKey]);
})->with([
    'nota acima de 5' => [['nota' => 6, 'recomenda' => 5], 'nota'],
    'nota zero' => [['nota' => 0, 'recomenda' => 5], 'nota'],
    'escala acima de 10' => [['nota' => 5, 'recomenda' => 11], 'recomenda'],
    'escala fracionada' => [['nota' => 5, 'recomenda' => 7.5], 'recomenda'],
]);

it('ignora blocos no envio: não geram valor', function () {
    submitSatisfaction(satisfactionForm(), ['nota' => 5, 'recomenda' => 8, 'titulo' => 'injetado', 'etapa_2' => 'x'])->assertCreated();

    expect(Submission::query()->sole()->data)->not->toHaveKeys(['titulo', 'etapa_2']);
});

it('não mostra no email do dono o campo que estava oculto', function () {
    Mail::fake();

    submitSatisfaction(satisfactionForm(), ['nota' => 5, 'recomenda' => 9])->assertCreated();

    Mail::assertSent(NewSubmissionMail::class, fn (NewSubmissionMail $mail): bool => array_column($mail->rows, 'field_id') === ['nota', 'recomenda']
        && $mail->rows[0]['value'] === '5 de 5'
        && $mail->rows[1]['value'] === '9 (de 0 a 10)');
});

it('na edição, descarta os arquivos de um campo que passou a ficar oculto', function () {
    $form = satisfactionForm(['allow_respondent_edit' => true]);
    $receipt = submitSatisfaction($form, ['nota' => 1, 'motivo' => 'Ruim demais', 'recomenda' => 1], [pdfUpload()])
        ->assertCreated()->json('data');
    $path = Media::query()->sole()->getPathRelativeToRoot();

    $this->withHeaders(['X-Edit-Token' => explode('#token=', $receipt['edit_url'])[1]])
        ->putJson("/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}", ['answers' => ['nota' => 4, 'motivo' => 'Ruim demais', 'recomenda' => 8]])
        ->assertOk();

    expect(Submission::query()->sole()->data['motivo'])->toBeNull()
        ->and(Media::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});
