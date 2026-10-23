<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\SubmissionReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SubmissionReceipt $resource
 */
final class SubmissionReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
