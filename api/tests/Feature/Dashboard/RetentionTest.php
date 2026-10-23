<?php

declare(strict_types=1);

use App\Actions\Submissions\DeleteSubmission;
use App\Jobs\ApplyRetentionPolicy;
use App\Jobs\PurgeExpiredExports;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function submitWithAttachment(Form $form): void
{
    test()->post(
        "/api/v1/forms/{$form->slug}/submissions",
        ['answers' => json_encode(validSubmissionAnswers()), 'files' => ['comprovante' => [UploadedFile::fake()->createWithContent('Recibo.pdf', "%PDF-1.4\n%%EOF\n")]]],
        ['Accept' => 'application/json'],
    )->assertCreated();
}

it('remove respostas mais antigas que o prazo, com os anexos', function () {
    $form = publishedForm(['retention_days' => 30]);
    $this->travelTo(now()->subDays(31));
    submitWithAttachment($form);
    $oldFile = Media::query()->sole()->getPathRelativeToRoot();
    $this->travelBack();
    submitWithAttachment($form);

    (new ApplyRetentionPolicy)->handle(app(DeleteSubmission::class));

    expect(Submission::query()->count())->toBe(1)
        ->and(Media::query()->count())->toBe(1)
        ->and($form->fresh()->submissions_count)->toBe(1);
    Storage::disk('local')->assertMissing($oldFile);
});

it('não mexe em formulários sem prazo de retenção', function () {
    $form = publishedForm();
    $this->travelTo(now()->subYears(2));
    submitWithAttachment($form);
    $this->travelBack();

    (new ApplyRetentionPolicy)->handle(app(DeleteSubmission::class));

    expect(Submission::query()->count())->toBe(1);
});

it('continua valendo para formulários excluídos', function () {
    $form = publishedForm(['retention_days' => 1]);
    $this->travelTo(now()->subDays(2));
    submitWithAttachment($form);
    $this->travelBack();
    $form->delete();

    (new ApplyRetentionPolicy)->handle(app(DeleteSubmission::class));

    expect(Submission::query()->count())->toBe(0);
});

it('processa em lotes e se reenfileira até terminar', function () {
    config(['submissions.retention_batch_size' => 2]);
    $form = publishedForm(['retention_days' => 1]);
    $this->travelTo(now()->subDays(2));
    foreach (range(1, 5) as $i) {
        test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])->assertCreated();
    }
    $this->travelBack();

    // Fila síncrona nos testes: o reenfileiramento roda na hora, até zerar.
    ApplyRetentionPolicy::dispatch();

    expect(Submission::query()->count())->toBe(0);
});

it('agenda a retenção diariamente e a limpeza de exportações de hora em hora', function () {
    $jobs = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => $event instanceof CallbackEvent)
        ->mapWithKeys(fn (CallbackEvent $event): array => [(string) $event->description => $event->expression]);

    expect($jobs[ApplyRetentionPolicy::class] ?? $jobs['retention-policy'] ?? null)->toBe('0 3 * * *')
        ->and($jobs[PurgeExpiredExports::class] ?? $jobs['purge-expired-exports'] ?? null)->toBe('0 * * * *');
});
