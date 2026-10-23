<?php

declare(strict_types=1);

namespace App\Jobs\Emails;

use App\Mail\SubmissionUpdatedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa o dono que o respondente editou a resposta (D15).
 *
 * Idempotência por edição (`editedAt`): a marca fica no cache, porque cada resposta
 * pode ser editada várias vezes e as revisões são imutáveis.
 */
final class SendSubmissionUpdatedEmail extends SubmissionEmailJob
{
    private const SENT_MARKER_TTL_SECONDS = 7 * 86_400;

    public function __construct(string $submissionId, public readonly string $editedAt)
    {
        parent::__construct($submissionId);
    }

    public function handle(): void
    {
        $submission = $this->loadSubmission();
        $marker = "submission-updated-email:{$this->submissionId}:{$this->editedAt}";

        if ($submission === null || Cache::has($marker)) {
            return;
        }

        $form = $this->formOf($submission);

        Mail::to($this->ownerRecipients($form))->send(new SubmissionUpdatedMail(
            formTitle: $form->title,
            rows: $this->presentAnswers($submission),
            fileGroups: $this->presentFiles($submission, withDownloadLinks: true),
            editedAt: $this->formatDate($submission->edited_at),
            dashboardUrl: $this->urls()->submissionInDashboard($form->id, $submission->id),
            settingsUrl: $this->urls()->formSettingsInDashboard($form->id),
        ));

        Cache::put($marker, true, self::SENT_MARKER_TTL_SECONDS);
    }
}
