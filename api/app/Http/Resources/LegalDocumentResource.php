<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LegalDocument $resource
 */
final class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'document' => $this->resource->value,
            'title' => $this->resource->title(),
            'version' => $this->resource->version(),
            'updated_at' => $this->resource->updatedAt(),
            'content_markdown' => $this->resource->content(),
        ];
    }
}
