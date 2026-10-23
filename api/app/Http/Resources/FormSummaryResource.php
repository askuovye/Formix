<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Form;
use App\Services\FrontendUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formulário na listagem: sem schema nem configurações, para manter a página leve.
 *
 * @property-read Form $resource
 */
class FormSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $form = $this->resource;

        return [
            'id' => $form->id,
            'title' => $form->title,
            'slug' => $form->slug,
            'status' => $form->status->value,
            'published_version' => $form->schema_version,
            'has_unpublished_changes' => $form->has_unpublished_changes,
            'public_url' => app(FrontendUrlGenerator::class)->publicForm($form->slug),
            'published_at' => $form->published_at?->toIso8601String(),
            'closed_at' => $form->closed_at?->toIso8601String(),
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];
    }
}
