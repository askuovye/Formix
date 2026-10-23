<?php

declare(strict_types=1);

namespace App\Jobs\Emails;

use App\Mail\NewSubmissionMail;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa o dono (ou os emails de notificação configurados) de uma resposta nova.
 */
final class SendNewSubmissionEmail extends SubmissionEmailJob
{
    public function handle(): void
    {
        $submission = $this->loadSubmission();

        if ($submission === null || $submission->owner_notified_at !== null) {
            return;
        }

        $form = $this->formOf($submission);

        Mail::to($this->ownerRecipients($form))->send(new NewSubmissionMail(
            formTitle: $form->title,
            rows: $this->presentAnswers($submission),
            fileGroups: $this->presentFiles($submission, withDownloadLinks: true),
            receivedAt: $this->formatDate($submission->created_at),
            dashboardUrl: $this->urls()->submissionInDashboard($form->id, $submission->id),
            settingsUrl: $this->urls()->formSettingsInDashboard($form->id),
        ));

        $submission->forceFill(['owner_notified_at' => now()])->save();
    }
}
