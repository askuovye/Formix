<?php

declare(strict_types=1);

namespace App\Jobs\Emails;

use App\Mail\RespondentCopyMail;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cópia ao respondente, no email que ele digitou no campo escolhido pelo dono.
 *
 * Criptografado na fila: o payload leva o token de edição em texto puro, e a
 * fila fica numa tabela do banco (D16).
 *
 * Limite por destinatário: o email é digitado por quem responde, então alguém
 * poderia usar o formulário para mandar cópias a um terceiro. No máximo
 * MAX_COPIES_PER_HOUR cópias por endereço por hora, somando todos os formulários.
 */
final class SendRespondentCopyEmail extends SubmissionEmailJob implements ShouldBeEncrypted
{
    public const MAX_COPIES_PER_HOUR = 3;

    private const RATE_LIMIT_DECAY_SECONDS = 3600;

    public function __construct(string $submissionId, #[\SensitiveParameter] public readonly ?string $editToken)
    {
        parent::__construct($submissionId);
    }

    public function handle(): void
    {
        $submission = $this->loadSubmission();

        if ($submission === null || $submission->respondent_notified_at !== null) {
            return;
        }

        $form = $this->formOf($submission);
        $recipient = $this->recipient($form, $submission);

        if ($recipient !== null) {
            RateLimiter::attempt(
                'respondent-copy:'.hash('sha256', mb_strtolower($recipient)),
                self::MAX_COPIES_PER_HOUR,
                fn () => Mail::to($recipient)->send($this->mail($form, $submission)),
                self::RATE_LIMIT_DECAY_SECONDS,
            );
        }

        // Marca como processado também quando não envia (sem email válido ou limite):
        // não há por que tentar de novo.
        $submission->forceFill(['respondent_notified_at' => now()])->save();
    }

    private function recipient(Form $form, Submission $submission): ?string
    {
        $field = $form->settings->respondentEmailField;

        if (! $form->settings->sendCopyToRespondent || $field === null) {
            return null;
        }

        $email = $submission->data[$field] ?? null;

        // A resposta já passou pela validação de email; a checagem aqui é defensiva.
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function mail(Form $form, Submission $submission): RespondentCopyMail
    {
        $editable = $this->editToken !== null && $submission->isEditable();

        return new RespondentCopyMail(
            formTitle: $form->title,
            rows: $this->presentAnswers($submission),
            fileGroups: $this->presentFiles($submission, withDownloadLinks: false),
            receivedAt: $this->formatDate($submission->created_at),
            editUrl: $editable ? $this->urls()->submissionEdit($form->slug, $submission->id, (string) $this->editToken) : null,
            editableUntil: $editable ? $this->formatDate($submission->editable_until) : null,
        );
    }
}
