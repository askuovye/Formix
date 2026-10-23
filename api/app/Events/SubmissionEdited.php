<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Submission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Resposta alterada pelo respondente dentro da janela de edição (D15).
 */
final readonly class SubmissionEdited implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Submission $submission) {}
}
