<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * Exclusão definitiva de uma resposta (pedido do dono, LGPD ou retenção).
 *
 * Sempre pelo model: o MediaLibrary apaga os anexos do disco no evento de
 * exclusão. Apagar direto no banco deixaria arquivos órfãos (docs/database.md).
 */
final class DeleteSubmission
{
    public function __invoke(Submission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            // Contador do limite de respostas acompanha a exclusão; sem descer abaixo de zero.
            Form::withTrashed()
                ->whereKey($submission->form_id)
                ->where('submissions_count', '>', 0)
                ->decrement('submissions_count');

            $submission->delete();
        });
    }
}
