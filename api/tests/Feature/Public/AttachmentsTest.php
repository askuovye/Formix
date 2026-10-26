<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Data\FormSettings;
use App\Mail\NewSubmissionMail;
use App\Mail\RespondentCopyMail;
use App\Models\Form;
use App\Models\Submission;
use App\Services\FormSchema\FormSchemaNormalizer;
use App\Services\Submissions\AttachmentLinks;
use App\Validation\FileAnswersValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function pdfFile(string $name = 'nota.pdf', int $paddingBytes = 0): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n".str_repeat('a', $paddingBytes)."\ntrailer<<>>\n%%EOF\n");
}

function pngFile(string $name = 'foto.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) base64_decode(ONE_PIXEL_PNG));
}

/**
 * Formulário publicado com o campo de arquivo "comprovante" configurado.
 *
 * @param  array<string, mixed>  $fileField
 * @param  array<string, mixed>  $settings
 */
function formWithFileField(array $fileField = [], array $settings = []): Form
{
    $schema = validFormSchema();
    $schema['fields'][4] = [...$schema['fields'][4], ...$fileField];

    $form = Form::factory()->create([
        'schema' => app(FormSchemaNormalizer::class)->normalize($schema),
        'settings' => FormSettings::fromArray($settings),
    ]);

    return app(PublishForm::class)($form, $form->user);
}

/**
 * @param  list<UploadedFile>  $files
 * @param  array<string, mixed>  $answers
 */
function submitWithFiles(Form $form, array $files, array $answers = [], array $extra = []): TestResponse
{
    return test()->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode([...validSubmissionAnswers(), ...$answers]), 'files' => ['comprovante' => $files], ...$extra],
        ['Accept' => 'application/json'],
    );
}

function storedMedia(): Media
{
    return Media::query()->sole();
}

it('grava os anexos no disco privado com nome gerado e o nome original só como metadado', function () {
    $form = formWithFileField();

    submitWithFiles($form, [pdfFile('Nota Fiscal Março.pdf')])->assertCreated();

    $submission = Submission::query()->sole();
    $media = storedMedia();

    expect($media->collection_name)->toBe('field_comprovante')
        ->and($media->model_id)->toBe($submission->id)
        ->and($media->disk)->toBe('local')
        ->and($media->file_name)->toMatch('/^[0-9a-f-]{36}\.pdf$/')
        ->and($media->name)->toBe('Nota Fiscal Março.pdf')
        ->and($media->getCustomProperty('field_id'))->toBe('comprovante')
        ->and($media->getCustomProperty('detected_mime_type'))->toBe('application/pdf')
        ->and($media->generated_conversions)->toBe([])
        ->and($submission->data)->not->toHaveKey('comprovante');

    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
});

it('usa a extensão do tipo real, nunca a enviada pelo cliente', function (string $clientName, string $expectedName) {
    $form = formWithFileField();

    submitWithFiles($form, [pdfFile($clientName)])->assertCreated();

    expect(storedMedia()->name)->toBe($expectedName)
        ->and(storedMedia()->file_name)->toEndWith('.pdf');
})->with([
    'extensão enganosa' => ['documento.exe', 'documento.pdf'],
    'extensão dupla' => ['contrato.pdf.bat', 'contrato.pdf.pdf'],
    'tentativa de caminho' => ['../../etc/passwd.pdf', 'passwd.pdf'],
    'caminho Windows' => ['C:\\Users\\fulano\\recibo.pdf', 'recibo.pdf'],
    'caracteres proibidos' => ['rel<at>ório"|?.pdf', 'rel at ório.pdf'],
    'sem nome' => ['.pdf', 'arquivo.pdf'],
]);

it('aceita vários arquivos até o limite do campo', function () {
    submitWithFiles(formWithFileField(), [pdfFile(), pngFile()])->assertCreated();

    expect(Media::query()->count())->toBe(2)
        ->and(Media::query()->pluck('file_name')->map(fn (string $name): string => pathinfo($name, PATHINFO_EXTENSION))->sort()->values()->all())
        ->toBe(['pdf', 'png']);
});

it('recusa arquivo cujo conteúdo não é do tipo aceito, mesmo com extensão permitida', function (UploadedFile $file) {
    $form = formWithFileField(['file' => ['accept' => ['pdf', 'image', 'text'], 'max_files' => 2, 'max_size_mb' => 5]]);

    submitWithFiles($form, [$file])
        ->assertUnprocessable()
        ->assertJsonPath('errors.comprovante.0', fn (string $message): bool => str_contains($message, 'não é de um tipo aceito'));

    expect(Submission::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'PHP disfarçado de PDF' => fn () => UploadedFile::fake()->createWithContent('nota.pdf', "<?php system(\$_GET['c']); ?>"),
    'SVG disfarçado de PNG' => fn () => UploadedFile::fake()->createWithContent('foto.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'),
    'HTML disfarçado de TXT' => fn () => UploadedFile::fake()->createWithContent('leia.txt', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'),
    'executável Windows disfarçado de PDF' => fn () => UploadedFile::fake()->createWithContent('nota.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\x00", 64).'This program cannot be run in DOS mode.'),
]);

it('recusa PNG quando o campo só aceita PDF', function () {
    $form = formWithFileField(['file' => ['accept' => ['pdf'], 'max_files' => 1, 'max_size_mb' => 5]]);

    submitWithFiles($form, [pngFile()])
        ->assertUnprocessable()
        ->assertJsonPath('errors.comprovante.0', 'O arquivo "foto.png" não é de um tipo aceito. Envie: PDF.');
});

it('aceita CSV em campo de planilha', function () {
    $form = formWithFileField(['file' => ['accept' => ['spreadsheet'], 'max_files' => 1, 'max_size_mb' => 5]]);

    submitWithFiles($form, [UploadedFile::fake()->createWithContent('dados.csv', "nome,idade\nAna,30\nBruno,25\n")])->assertCreated();

    expect(storedMedia()->getCustomProperty('detected_mime_type'))->toBe('text/csv')
        ->and(storedMedia()->file_name)->toEndWith('.csv');
});

it('respeita a quantidade máxima de arquivos do campo', function () {
    submitWithFiles(formWithFileField(), [pdfFile('a.pdf'), pdfFile('b.pdf'), pdfFile('c.pdf')])
        ->assertUnprocessable()
        ->assertJsonPath('errors.comprovante.0', 'Envie no máximo 2 arquivo(s) em "Comprovante".');
});

it('respeita o tamanho máximo por arquivo', function () {
    $form = formWithFileField(['file' => ['accept' => ['pdf'], 'max_files' => 1, 'max_size_mb' => 1]]);

    submitWithFiles($form, [pdfFile('grande.pdf', paddingBytes: 1100 * 1024)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.comprovante.0', 'O arquivo "grande.pdf" passa do limite de 1 MB.');
});

it('respeita o limite total de arquivos por envio', function () {
    config(['submissions.max_total_upload_mb' => 1]);

    submitWithFiles(formWithFileField(), [pdfFile('a.pdf', 600 * 1024), pdfFile('b.pdf', 600 * 1024)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.'.FileAnswersValidator::TOTAL_ERROR_KEY.'.0', 'Os arquivos enviados somam mais de 1 MB.');
});

it('exige arquivo em campo obrigatório', function () {
    submitWithFiles(formWithFileField(['required' => true]), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.comprovante.0', 'O campo Comprovante é obrigatório.');
});

it('devolve juntos os erros das respostas e dos arquivos', function () {
    submitWithFiles(formWithFileField(['required' => true]), [], ['nome' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nome', 'comprovante']);
});

it('continua aceitando envio em JSON quando não há arquivo obrigatório', function () {
    $this->postJson('/api/v1/forms/'.formWithFileField()->slug.'/submissions', ['answers' => validSubmissionAnswers()])->assertCreated();
});

it('não grava arquivos de bots que preenchem o honeypot', function () {
    submitWithFiles(formWithFileField(), [pdfFile()], extra: ['website' => 'spam'])->assertCreated();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('entrega o arquivo pelo link assinado, sempre como download e sem permitir execução', function () {
    submitWithFiles(formWithFileField(), [pdfFile('Recibo.pdf')])->assertCreated();
    $media = storedMedia();

    $response = $this->get(app(AttachmentLinks::class)->temporaryUrl($media))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain('Recibo.pdf')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->streamedContent())->toStartWith('%PDF-1.4');
});

it('recusa link adulterado ou expirado', function () {
    submitWithFiles(formWithFileField(), [pdfFile()])->assertCreated();
    $url = app(AttachmentLinks::class)->temporaryUrl(storedMedia());

    $this->get(preg_replace('/signature=\w+/', 'signature=adulterada', $url))->assertForbidden();

    $this->travel((int) config('submissions.attachment_link_ttl_days') + 1)->days();
    $this->get($url)->assertForbidden();
});

it('não entrega anexo inexistente ou de formulário excluído, mesmo com link válido', function () {
    $form = formWithFileField();
    submitWithFiles($form, [pdfFile()])->assertCreated();
    $url = app(AttachmentLinks::class)->temporaryUrl(storedMedia());

    $this->get(URL::temporarySignedRoute(AttachmentLinks::ROUTE, now()->addHour(), ['media' => (string) Str::uuid()]))->assertNotFound();

    $form->delete();
    $this->get($url)->assertNotFound();
});

it('não aceita ID sequencial no lugar do UUID', function () {
    submitWithFiles(formWithFileField(), [pdfFile()])->assertCreated();

    $this->get(URL::temporarySignedRoute(AttachmentLinks::ROUTE, now()->addHour(), ['media' => (string) storedMedia()->id]))->assertNotFound();
});

it('manda os links assinados no email ao dono e só os nomes na cópia ao respondente', function () {
    Mail::fake();
    $form = formWithFileField(settings: ['send_copy_to_respondent' => true, 'respondent_email_field' => 'email']);

    submitWithFiles($form, [pdfFile('Recibo.pdf')])->assertCreated();

    Mail::assertSent(NewSubmissionMail::class, function (NewSubmissionMail $mail): bool {
        $file = $mail->fileGroups[0]['files'][0];

        return $mail->fileGroups[0]['label'] === 'Comprovante'
            && $file['name'] === 'Recibo.pdf'
            && str_contains((string) $file['url'], '/api/v1/files/')
            && str_contains((string) $file['url'], 'signature=');
    });

    Mail::assertSent(RespondentCopyMail::class, fn (RespondentCopyMail $mail): bool => $mail->fileGroups[0]['files'][0]['name'] === 'Recibo.pdf'
        && $mail->fileGroups[0]['files'][0]['url'] === null);
});

it('na edição, troca os arquivos do campo e guarda os metadados dos anteriores', function () {
    $form = formWithFileField(settings: ['allow_respondent_edit' => true]);
    $receipt = submitWithFiles($form, [pdfFile('Original.pdf')])->assertCreated()->json('data');
    $token = explode('#token=', $receipt['edit_url'])[1];
    $oldPath = storedMedia()->getPathRelativeToRoot();
    $oldSize = (int) storedMedia()->size;

    $this->post(
        "/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}",
        ['_method' => 'PUT', 'answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [pngFile('Nova.png')]]],
        ['Accept' => 'application/json', 'X-Edit-Token' => $token],
    )->assertOk();

    expect(storedMedia()->name)->toBe('Nova.png')
        ->and(Submission::query()->sole()->revisions->first()->files)->toBe([
            ['field_id' => 'comprovante', 'name' => 'Original.pdf', 'size' => $oldSize, 'mime_type' => 'application/pdf'],
        ]);

    Storage::disk('local')->assertMissing($oldPath);
});

it('na edição, mostra os arquivos atuais só com nome e tamanho, já trocados depois de editar', function () {
    $form = formWithFileField(['file' => ['accept' => ['pdf', 'image'], 'max_files' => 2, 'max_size_mb' => 5]], ['allow_respondent_edit' => true]);
    $receipt = submitWithFiles($form, [pdfFile('Original.pdf'), pngFile('Foto.png')])->assertCreated()->json('data');
    $token = explode('#token=', $receipt['edit_url'])[1];
    $url = "/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}";

    $files = $this->withHeaders(['X-Edit-Token' => $token])->getJson($url)->assertOk()->json('data.files');

    expect($files)->toHaveCount(1)
        ->and($files[0]['field_id'])->toBe('comprovante')
        ->and(array_column($files[0]['files'], 'name'))->toBe(['Original.pdf', 'Foto.png'])
        ->and($files[0]['files'][0])->toHaveKeys(['name', 'size'])
        ->and($files[0]['files'][0])->not->toHaveKey('url');

    $this->post(
        $url,
        ['_method' => 'PUT', 'answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [pngFile('Nova.png')]]],
        ['Accept' => 'application/json', 'X-Edit-Token' => $token],
    )->assertOk()->assertJsonPath('data.files.0.files.0.name', 'Nova.png')->assertJsonCount(1, 'data.files.0.files');
});

it('na edição, mantém os arquivos quando nenhum novo é enviado, inclusive em campo obrigatório', function () {
    $form = formWithFileField(['required' => true], ['allow_respondent_edit' => true]);
    $receipt = submitWithFiles($form, [pdfFile('Original.pdf')])->assertCreated()->json('data');
    $token = explode('#token=', $receipt['edit_url'])[1];

    $this->withHeaders(['X-Edit-Token' => $token])
        ->putJson("/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}", ['answers' => [...validSubmissionAnswers(), 'nome' => 'Maria Lima']])
        ->assertOk();

    expect(storedMedia()->name)->toBe('Original.pdf');
});
