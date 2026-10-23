<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FormAvailability;
use App\Models\Form;
use App\Services\FormAssets;
use App\Services\FrontendUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O que a página pública recebe: conteúdo da versão publicada e se aceita
 * respostas. Nada de dono, configurações internas ou contagem de respostas.
 *
 * @property-read array{form: Form, version: array{version: int, title: string, description: string|null, schema: array{fields: list<array<string, mixed>>}}, availability: FormAvailability} $resource
 */
final class PublicFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['form' => $form, 'version' => $version, 'availability' => $availability] = $this->resource;
        $accepts = $availability->acceptsSubmissions();

        return [
            'slug' => $form->slug,
            'version' => $version['version'],
            'title' => $version['title'],
            'description' => $version['description'],
            'accepts_submissions' => $accepts,
            'availability' => $availability->value,
            'unavailable_message' => $availability->message(),
            // Sem formulário aberto não há por que entregar os campos.
            'schema' => $accepts ? app(FormAssets::class)->withUrls($version['schema']) : null,
            'opens_at' => $form->settings->opensAt?->utc()->toIso8601String(),
            'closes_at' => $form->settings->closesAt?->utc()->toIso8601String(),
            'allows_respondent_edit' => $form->settings->allowRespondentEdit,
            'privacy_policy_url' => app(FrontendUrlGenerator::class)->privacyPolicy(),
        ];
    }
}
