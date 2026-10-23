<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @property-read Export $resource
 */
final class ExportResource extends JsonResource
{
    public const DOWNLOAD_ROUTE = 'exports.download';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $export = $this->resource;

        return [
            'id' => $export->id,
            'form_id' => $export->form_id,
            'status' => $export->status->value,
            'from' => $export->from?->toIso8601String(),
            'to' => $export->to?->toIso8601String(),
            'rows_count' => $export->rows_count,
            // Link novo a cada consulta, curto (o arquivo em si expira em `expires_at`).
            'download_url' => $export->isDownloadable()
                ? URL::temporarySignedRoute(self::DOWNLOAD_ROUTE, now()->addMinutes((int) config('submissions.export_link_ttl_minutes')), ['export' => $export->id])
                : null,
            'created_at' => $export->created_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
        ];
    }
}
