<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta aberta pelo link de edição: os campos da versão com que foi enviada
 * e as respostas atuais, para a página pública preencher o formulário.
 *
 * @property-read array{submission: Submission, version: array{version: int, title: string, description: string|null, schema: array{fields: list<array<string, mixed>>}}} $resource
 */
final class EditableSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['submission' => $submission, 'version' => $version] = $this->resource;

        return [
            'id' => $submission->id,
            'version' => $version['version'],
            'title' => $version['title'],
            'description' => $version['description'],
            'schema' => $version['schema'],
            'answers' => $submission->data,
            'editable_until' => $submission->editable_until?->utc()->toIso8601String(),
            'edited_at' => $submission->edited_at?->utc()->toIso8601String(),
        ];
    }
}
