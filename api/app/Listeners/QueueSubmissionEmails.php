<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubmissionEdited;
use App\Events\SubmissionReceived;
use App\Jobs\Emails\SendNewSubmissionEmail;
use App\Jobs\Emails\SendRespondentCopyEmail;
use App\Jobs\Emails\SendSubmissionUpdatedEmail;

/**
 * Só enfileira: o envio (lento, externo) acontece nos Jobs da fila `emails`.
 * Os eventos já são disparados depois do commit da resposta.
 *
 * A cópia ao respondente é sempre enfileirada; o Job confere as configurações
 * do formulário no momento do envio (o dono pode tê-las mudado nesse meio-tempo).
 */
final class QueueSubmissionEmails
{
    public function handleReceived(SubmissionReceived $event): void
    {
        SendNewSubmissionEmail::dispatch($event->submission->id);
        SendRespondentCopyEmail::dispatch($event->submission->id, $event->editToken);
    }

    public function handleEdited(SubmissionEdited $event): void
    {
        $editedAt = $event->submission->edited_at?->toIso8601String() ?? (string) now()->timestamp;

        SendSubmissionUpdatedEmail::dispatch($event->submission->id, $editedAt);
    }
}
