<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Enums\ExportStatus;
use App\Exceptions\ApiException;
use App\Jobs\GenerateSubmissionsExport;
use App\Models\Export;
use App\Models\Form;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Registra o pedido e enfileira a geração do CSV (fila `exports`): exportar
 * milhares de respostas não cabe no tempo de uma requisição.
 */
final class RequestSubmissionsExport
{
    public const MAX_ACTIVE_EXPORTS = 3;

    public const TOO_MANY_MESSAGE = 'Você já tem exportações em andamento. Aguarde uma terminar para pedir outra.';

    public function __invoke(User $owner, Form $form, ?CarbonImmutable $from, ?CarbonImmutable $to): Export
    {
        $active = Export::query()
            ->where('user_id', $owner->id)
            ->whereIn('status', [ExportStatus::Pending, ExportStatus::Processing])
            ->count();

        if ($active >= self::MAX_ACTIVE_EXPORTS) {
            throw ApiException::conflict(self::TOO_MANY_MESSAGE);
        }

        $export = new Export;
        $export->forceFill([
            'user_id' => $owner->id,
            'form_id' => $form->id,
            'status' => ExportStatus::Pending,
            'from' => $from,
            'to' => $to,
        ])->save();

        GenerateSubmissionsExport::dispatch($export->id);

        return $export;
    }
}
