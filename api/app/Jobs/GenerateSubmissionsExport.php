<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Submissions\ListOwnerSubmissions;
use App\Data\SubmissionFilters;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Enums\QueueName;
use App\Models\Export;
use App\Models\Form;
use App\Models\User;
use App\Services\Submissions\SubmissionsCsvWriter;
use App\Services\Submissions\SubmissionsPdfRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Gera o arquivo (CSV ou PDF) de uma exportação na fila `exports` (conexão `database-long`, D5).
 *
 * Lê as respostas em lotes (`lazyById`) e escreve num stream temporário, que o
 * PHP passa para disco acima de alguns MB: memória estável mesmo com muitas linhas.
 */
final class GenerateSubmissionsExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    private const CHUNK_SIZE = 500;

    public function __construct(public readonly string $exportId)
    {
        $this->onConnection((string) config('queue.long_connection'));
        $this->onQueue(QueueName::Exports->value);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ListOwnerSubmissions $listSubmissions, SubmissionsCsvWriter $writer, SubmissionsPdfRenderer $pdf): void
    {
        $export = Export::query()->with(['form', 'user'])->find($this->exportId);

        // Idempotente: exportação apagada ou já concluída não é refeita.
        if ($export === null || ! $export->status->isActive()) {
            return;
        }

        $form = $export->form;
        $owner = $export->user;

        if (! $form instanceof Form || ! $owner instanceof User) {
            $this->markFailed($export);

            return;
        }

        $export->forceFill(['status' => ExportStatus::Processing])->save();

        $filters = new SubmissionFilters(
            formId: $form->id,
            from: $export->from?->toImmutable(),
            to: $export->to?->toImmutable(),
            oldestFirst: true,
            search: $export->search,
        );

        $disk = (string) config('submissions.export_disk');
        $path = "exports/{$export->id}.{$export->format->value}";

        if ($export->format === ExportFormat::Pdf) {
            // Teto de linhas conferido ao pedir (RequestSubmissionsExport): cabe em memória.
            $submissions = $listSubmissions->query($owner, $filters)->with('media')->orderBy('id')->get();
            Storage::disk($disk)->put($path, $pdf->render($form, $submissions));
            $this->markReady($export, $disk, $path, $submissions->count());

            return;
        }

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo temporário da exportação.');
        }

        try {
            $rows = $writer->write(
                $stream,
                $form,
                $listSubmissions->query($owner, $filters)->with('media')->lazyById(self::CHUNK_SIZE),
            );

            rewind($stream);
            Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        $this->markReady($export, $disk, $path, $rows);
    }

    private function markReady(Export $export, string $disk, string $path, int $rows): void
    {
        $export->forceFill([
            'status' => ExportStatus::Ready,
            'disk' => $disk,
            'path' => $path,
            'rows_count' => $rows,
            'completed_at' => now(),
            'expires_at' => CarbonImmutable::now()->addHours((int) config('submissions.export_ttl_hours')),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $export = Export::query()->find($this->exportId);

        if ($export !== null) {
            $this->markFailed($export);
        }

        Log::warning('Falha ao gerar exportação de respostas.', [
            'export_id' => $this->exportId,
            'exception' => $exception::class,
        ]);
    }

    private function markFailed(Export $export): void
    {
        $export->forceFill(['status' => ExportStatus::Failed, 'completed_at' => now()])->save();
    }
}
