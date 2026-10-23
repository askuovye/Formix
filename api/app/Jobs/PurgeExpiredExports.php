<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\QueueName;
use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga arquivos e registros de exportações vencidas (agendado a cada hora).
 * O CSV tem dados pessoais: não fica no servidor além do necessário (LGPD).
 */
final class PurgeExpiredExports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Exportações que falharam ou travaram somem depois disso.
    private const STALE_AFTER_HOURS = 48;

    public function __construct()
    {
        $this->onQueue(QueueName::Default->value);
    }

    public function handle(): void
    {
        Export::query()
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '<=', now())
                ->orWhere(fn (Builder $stale) => $stale
                    ->where('status', '!=', ExportStatus::Ready)
                    ->where('created_at', '<=', now()->subHours(self::STALE_AFTER_HOURS))))
            ->lazyById()
            ->each(function (Export $export): void {
                if ($export->disk !== null && $export->path !== null) {
                    Storage::disk($export->disk)->delete($export->path);
                }

                $export->delete();
            });
    }
}
