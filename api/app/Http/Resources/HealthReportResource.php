<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Health\HealthReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read HealthReport $resource
 */
final class HealthReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'checks' => $this->resource->checks,
        ];
    }
}
