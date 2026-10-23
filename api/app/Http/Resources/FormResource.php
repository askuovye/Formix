<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\FormAssets;
use Illuminate\Http\Request;

/**
 * Formulário completo para o builder: rascunho do schema e configurações.
 */
final class FormResource extends FormSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->resource->description,
            'schema' => app(FormAssets::class)->withUrls($this->resource->schema),
            'settings' => $this->resource->settings->toArray(),
        ];
    }
}
