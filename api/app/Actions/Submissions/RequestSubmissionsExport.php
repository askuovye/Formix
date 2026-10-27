<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Data\SubmissionFilters;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Exceptions\ApiException;
use App\Jobs\GenerateSubmissionsExport;
use App\Models\Export;
use App\Models\Form;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Registra o pedido e enfileira a geração do CSV (fila `exports`): exportar
 * milhares de respostas não cabe no tempo de uma requisição.
 */
final class RequestSubmissionsExport
{
    public const MAX_ACTIVE_EXPORTS = 3;

    public const TOO_MANY_MESSAGE = 'Você já tem exportações em andamento. Aguarde uma terminar para pedir outra.';

    public function __construct(private readonly ListOwnerSubmissions $listSubmissions) {}

    public function __invoke(
        User $owner,
        Form $form,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        ?string $search = null,
        ExportFormat $format = ExportFormat::Csv,
        ?string $respondentEmail = null,
    ): Export {
        if ($format === ExportFormat::Pdf) {
            $this->ensureFitsInPdf($owner, new SubmissionFilters(formId: $form->id, from: $from, to: $to, search: $search, respondentEmail: $respondentEmail));
        }

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
            'format' => $format,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'respondent_email' => $respondentEmail,
        ])->save();

        GenerateSubmissionsExport::dispatch($export->id);

        return $export;
    }

    /**
     * O dompdf monta o documento inteiro em memória: PDF tem teto de respostas
     * (o CSV não tem). Acima dele, pedir para filtrar ou usar o CSV.
     */
    private function ensureFitsInPdf(User $owner, SubmissionFilters $filters): void
    {
        $max = (int) config('submissions.pdf_export_max_rows');

        if ($this->listSubmissions->query($owner, $filters)->count() > $max) {
            throw ValidationException::withMessages([
                'format' => "O PDF aceita até {$max} respostas. Filtre por período ou busca, ou exporte em CSV.",
            ]);
        }
    }
}
