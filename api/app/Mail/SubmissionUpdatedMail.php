<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SubmissionUpdatedMail extends SubmissionMail
{
    /**
     * @param  list<array{field_id: string, label: string, value: string, multiline: bool, answered: bool}>  $rows
     * @param  list<array{field_id: string, label: string, files: list<array{name: string, size: string, url: string|null}>}>  $fileGroups
     */
    public function __construct(
        public readonly string $formTitle,
        public readonly array $rows,
        public readonly array $fileGroups,
        public readonly string $editedAt,
        public readonly string $dashboardUrl,
        public readonly string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectWith('Resposta atualizada', $this->formTitle));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.submissions.updated',
            text: 'mail.submissions.updated-text',
        );
    }
}
