<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Cópia das respostas para quem respondeu. Sem links de anexos: o respondente
 * já tem os arquivos, e o link poderia ser repassado a terceiros (D28).
 */
final class RespondentCopyMail extends SubmissionMail
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
        public readonly ?string $editUrl,
        public readonly ?string $editableUntil,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectWith('Cópia da sua resposta', $this->formTitle));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.submissions.respondent-copy',
            text: 'mail.submissions.respondent-copy-text',
        );
    }
}
