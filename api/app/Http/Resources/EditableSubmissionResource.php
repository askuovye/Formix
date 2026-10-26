<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Submission;
use App\Services\FormAssets;
use App\Services\FormSchema\SchemaItems;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta aberta pelo link de edição: os campos da versão com que foi enviada
 * e as respostas atuais, para a página pública preencher o formulário. Os
 * anexos atuais vão só com nome e tamanho (sem link): a pessoa vê o que enviou
 * e decide se substitui.
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
            'schema' => app(FormAssets::class)->withUrls($version['schema']),
            'answers' => $submission->data,
            'files' => $this->currentFiles($submission, $version['schema']),
            'editable_until' => $submission->editable_until?->utc()->toIso8601String(),
            'edited_at' => $submission->edited_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * Arquivos atuais por campo, na ordem do schema (campos sem arquivo ficam de fora).
     *
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @return list<array{field_id: string, files: list<array{name: string, size: int}>}>
     */
    private function currentFiles(Submission $submission, array $schema): array
    {
        $byField = [];

        // Consulta nova: depois de uma edição, a relação carregada pode ter arquivos já trocados.
        foreach ($submission->media()->orderBy('order_column')->get() as $media) {
            $byField[(string) $media->getCustomProperty('field_id')][] = ['name' => $media->name, 'size' => (int) $media->size];
        }

        $groups = [];

        foreach (SchemaItems::inputs($schema) as $field) {
            $id = (string) $field['id'];

            if (isset($byField[$id])) {
                $groups[] = ['field_id' => $id, 'files' => $byField[$id]];
            }
        }

        return $groups;
    }
}
