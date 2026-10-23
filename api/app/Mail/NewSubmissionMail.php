<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class NewSubmissionMail extends SubmissionMail
{
    /**
     * @param  list<array{field_id: string, label: string, value: string, multiline: bool, answered: bool}>  $rows
     * @param  list<array{field_id: string, label: string, files: list<array{name: string, size: string, url: string|null}>}>  $fileGroups
     */
    public function __construct(
        public readonly string $formTitle,
        public readonly array $rows,
        public readonly array $fileGroups,
        public readonly string $receivedAt,
        public readonly string $dashboardUrl,
        public readonly string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectWith('Nova resposta', $this->formTitle));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.submissions.new',
            text: 'mail.submissions.new-text',
        );
    }
}
