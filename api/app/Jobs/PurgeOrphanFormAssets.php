<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\FormAsset;
use App\Services\FormAssets;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Apaga imagens de tema enviadas e nunca usadas (nem no rascunho nem em versão
 * publicada), depois de um prazo para o dono terminar de editar. Agendado diariamente.
 */
final class PurgeOrphanFormAssets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const ORPHAN_AFTER_HOURS = 24;

    public function __construct()
    {
        $this->onQueue(QueueName::Default->value);
    }

    public function handle(FormAssets $assets): void
    {
        FormAsset::query()
            ->where('created_at', '<=', now()->subHours(self::ORPHAN_AFTER_HOURS))
            ->lazyById()
            ->each(function (FormAsset $asset) use ($assets): void {
                if (! $assets->isReferenced($asset)) {
                    $assets->destroy($asset);
                }
            });
    }
}
