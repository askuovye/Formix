<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Submissions\DeleteSubmission;
use App\Enums\QueueName;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Remove respostas mais antigas que o prazo de retenção de cada formulário
 * (`settings.retention_days`), agendado uma vez por dia.
 *
 * Remove no máximo `retention_batch_size` por execução (Jobs curtos, D16) e se
 * reenfileira se ainda sobrar. Exclui pelo model, que apaga os anexos do disco.
 * Vale também para formulários excluídos: o prazo continua correndo.
 */
final class ApplyRetentionPolicy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const FORMS_CHUNK = 100;

    private const REDISPATCH_DELAY_SECONDS = 60;

    public function __construct()
    {
        $this->onQueue(QueueName::Default->value);
    }

    public function handle(DeleteSubmission $deleteSubmission): void
    {
        $remaining = (int) config('submissions.retention_batch_size');

        // `settings` é JSON: o filtro por prazo é feito em PHP (sem funções de JSON
        // específicas de banco; AGENTS.md §6 "Filas").
        foreach (Form::withTrashed()->lazyById(self::FORMS_CHUNK) as $form) {
            $days = $form->settings->retentionDays;

            if ($days === null) {
                continue;
            }

            $expired = Submission::query()
                ->where('form_id', $form->id)
                ->where('created_at', '<', now()->subDays($days))
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            $expired->each(fn (Submission $submission) => $deleteSubmission($submission));
            $remaining -= $expired->count();

            if ($remaining <= 0) {
                self::dispatch()->delay(self::REDISPATCH_DELAY_SECONDS);

                return;
            }
        }
    }
}
