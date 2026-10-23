<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionRevision;
use App\Services\Submissions\AttachmentLinks;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\SubmissionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resposta completa no painel do dono: respostas legíveis (na ordem e com os
 * rótulos da versão em que foi enviada), anexos com link assinado e o histórico
 * de edições do respondente.
 *
 * @property-read Submission $resource
 */
final class SubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $submission = $this->resource;

        /** @var Form $form */
        $form = $submission->form;
        $schema = app(PublishedFormVersions::class)->get($form, $submission->schema_version)['schema'];
        $presenter = app(SubmissionPresenter::class);
        $links = app(AttachmentLinks::class);

        return [
            'id' => $submission->id,
            'form' => ['id' => $form->id, 'title' => $form->title, 'slug' => $form->slug],
            'schema_version' => $submission->schema_version,
            'answers' => $presenter->present($schema, $submission->data),
            'files' => $presenter->presentFiles($schema, $submission->media, fn (Media $media): string => $links->temporaryUrl($media)),
            'revisions' => $submission->revisions
                ->sortByDesc('replaced_at')
                ->map(fn (SubmissionRevision $revision): array => [
                    'replaced_at' => $revision->replaced_at->toIso8601String(),
                    'answers' => $presenter->present($schema, $revision->data),
                    'replaced_files' => $revision->files ?? [],
                ])
                ->values()
                ->all(),
            'created_at' => $submission->created_at?->toIso8601String(),
            'edited_at' => $submission->edited_at?->toIso8601String(),
            'editable_until' => $submission->editable_until?->toIso8601String(),
        ];
    }
}
