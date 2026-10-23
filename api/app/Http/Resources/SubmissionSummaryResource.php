<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Form;
use App\Models\Submission;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\SubmissionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Resposta na listagem do painel: metadados e uma prévia das primeiras respostas.
 *
 * @property-read Submission $resource
 */
final class SubmissionSummaryResource extends JsonResource
{
    private const PREVIEW_FIELDS = 2;

    private const PREVIEW_MAX_LENGTH = 120;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $submission = $this->resource;

        /** @var Form $form */
        $form = $submission->form;

        return [
            'id' => $submission->id,
            'form' => ['id' => $form->id, 'title' => $form->title],
            'schema_version' => $submission->schema_version,
            'preview' => $this->preview($form, $submission),
            'files_count' => (int) $submission->getAttribute('media_count'),
            'created_at' => $submission->created_at?->toIso8601String(),
            'edited_at' => $submission->edited_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function preview(Form $form, Submission $submission): array
    {
        $schema = app(PublishedFormVersions::class)->get($form, $submission->schema_version)['schema'];
        $answered = array_filter(app(SubmissionPresenter::class)->present($schema, $submission->data), fn (array $row): bool => $row['answered']);

        return array_map(
            fn (array $row): array => ['label' => $row['label'], 'value' => Str::limit($row['value'], self::PREVIEW_MAX_LENGTH)],
            array_slice(array_values($answered), 0, self::PREVIEW_FIELDS),
        );
    }
}
