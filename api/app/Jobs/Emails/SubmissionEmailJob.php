<?php

declare(strict_types=1);

namespace App\Jobs\Emails;

use App\Enums\QueueName;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\FrontendUrlGenerator;
use App\Services\Submissions\AttachmentLinks;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\SubmissionPresenter;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Base dos emails de resposta (fila `emails`).
 *
 * Idempotência: cada Job confere uma marca antes de enviar e grava a marca logo
 * depois. `WithoutOverlapping` impede duas execuções simultâneas para a mesma
 * resposta. Recebe só o ID: a resposta é lida do banco na hora de enviar, e se
 * tiver sido excluída (ou o formulário), o Job termina sem enviar nada.
 */
abstract class SubmissionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    private const DATE_FORMAT = 'd/m/Y \à\s H:i';

    private const DISPLAY_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(public readonly string $submissionId)
    {
        $this->onQueue(QueueName::Emails->value);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("submission-email:{$this->submissionId}"))->releaseAfter(30)->expireAfter(120)];
    }

    public function failed(Throwable $exception): void
    {
        // Só identificadores e tipo do erro: nada de destinatário ou conteúdo (AGENTS.md §6).
        Log::warning('Falha definitiva ao enviar email de resposta.', [
            'job' => static::class,
            'submission_id' => $this->submissionId,
            'exception' => $exception::class,
        ]);
    }

    protected function loadSubmission(): ?Submission
    {
        $submission = Submission::query()->with(['form.user', 'media'])->find($this->submissionId);

        // `form` vem nulo se o formulário foi excluído (SoftDeletes).
        return $submission?->form !== null ? $submission : null;
    }

    protected function formOf(Submission $submission): Form
    {
        /** @var Form $form */
        $form = $submission->form;

        return $form;
    }

    /**
     * Emails de notificação configurados ou, sem eles, o email do dono.
     *
     * @return list<string>
     */
    protected function ownerRecipients(Form $form): array
    {
        if ($form->settings->notificationEmails !== []) {
            return $form->settings->notificationEmails;
        }

        /** @var User $owner */
        $owner = $form->user;

        return [$owner->email];
    }

    /**
     * @return list<array{field_id: string, label: string, value: string, multiline: bool, answered: bool}>
     */
    protected function presentAnswers(Submission $submission): array
    {
        $version = app(PublishedFormVersions::class)->get($this->formOf($submission), $submission->schema_version);

        return app(SubmissionPresenter::class)->present($version['schema'], $submission->data);
    }

    /**
     * @return list<array{field_id: string, label: string, files: list<array{name: string, size: string, url: string|null}>}>
     */
    protected function presentFiles(Submission $submission, bool $withDownloadLinks): array
    {
        $version = app(PublishedFormVersions::class)->get($this->formOf($submission), $submission->schema_version);
        $links = app(AttachmentLinks::class);

        return app(SubmissionPresenter::class)->presentFiles(
            $version['schema'],
            $submission->media,
            $withDownloadLinks ? fn (Media $media): string => $links->temporaryUrl($media) : null,
        );
    }

    protected function formatDate(?DateTimeInterface $date): string
    {
        return $date === null ? '' : CarbonImmutable::instance($date)->setTimezone(self::DISPLAY_TIMEZONE)->format(self::DATE_FORMAT);
    }

    protected function urls(): FrontendUrlGenerator
    {
        return app(FrontendUrlGenerator::class);
    }
}
