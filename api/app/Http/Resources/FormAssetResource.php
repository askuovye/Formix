<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FormAsset;
use App\Services\FormAssets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FormAsset $resource
 */
final class FormAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind->value,
            'url' => app(FormAssets::class)->url($this->resource->id),
            'mime_type' => $this->resource->mime_type,
            'size' => $this->resource->size,
        ];
    }
}
