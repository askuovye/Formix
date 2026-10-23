<?php

declare(strict_types=1);

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A fila é processada por cron (decisão D16): job pendente há muito tempo
 * indica que o cron parou. Não é crítico porque a API continua recebendo
 * respostas; elas só ficam aguardando processamento.
 */
final class QueueBacklogHealthCheck implements HealthCheck
{
    private const MAX_PENDING_SECONDS = 600;

    public function name(): string
    {
        return 'queue';
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function passes(): bool
    {
        try {
            /** @var string|null $connection */
            $connection = config('queue.connections.database.connection');
            /** @var string $table */
            $table = config('queue.connections.database.table');

            return ! DB::connection($connection)
                ->table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '<', now()->subSeconds(self::MAX_PENDING_SECONDS)->getTimestamp())
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
