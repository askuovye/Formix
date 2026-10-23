<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Submission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado só depois do commit da resposta: quem ouvir (email ao dono, cópia
 * ao respondente) nunca recebe uma resposta que acabou desfeita.
 *
 * O token de edição em texto puro só existe neste momento; quem precisar dele
 * (email de cópia) deve tratá-lo como segredo (fila criptografada).
 */
final readonly class SubmissionReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Submission $submission,
        #[\SensitiveParameter] public ?string $editToken,
    ) {}
}
