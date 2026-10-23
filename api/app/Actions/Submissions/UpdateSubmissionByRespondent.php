<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Events\SubmissionEdited;
use App\Models\Form;
use App\Models\Submission;
use App\Services\Submissions\AttachmentStorage;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\RespondentEditGuard;
use App\Validation\ResponseValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Edição pelo respondente (D15). Campos de arquivo: enviar arquivos novos para
 * um campo substitui os anteriores daquele campo; não enviar mantém os atuais.
 */
final class UpdateSubmissionByRespondent
{
    public function __construct(
        private readonly RespondentEditGuard $guard,
        private readonly PublishedFormVersions $versions,
        private readonly ResponseValidator $validator,
        private readonly AttachmentStorage $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $files
     */
    public function __invoke(Form $form, Submission $submission, ?string $token, array $answers, array $files = []): Submission
    {
        $this->guard->authorize($form, $submission, $token);

        // Valida contra a versão com que a resposta foi enviada, não a atual:
        // o formulário pode ter sido republicado com outros campos.
        $schema = $this->versions->get($form, $submission->schema_version)['schema'];
        $currentMedia = $submission->getMedia('*');
        $validated = $this->validator->validate($schema, $answers, $files, $this->countByField($currentMedia));

        $replaced = $currentMedia->filter(fn (Media $media): bool => array_key_exists((string) $media->getCustomProperty('field_id'), $validated['files']));
        $storedFiles = [];

        try {
            $submission = DB::transaction(function () use ($submission, $validated, $replaced, &$storedFiles): Submission {
                $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);

                $locked->revisions()->create([
                    'data' => $locked->data,
                    'files' => $replaced->isEmpty() ? null : $replaced->map(fn (Media $media): array => [
                        'field_id' => (string) $media->getCustomProperty('field_id'),
                        'name' => $media->name,
                        'size' => (int) $media->size,
                        'mime_type' => (string) $media->mime_type,
                    ])->values()->all(),
                    'replaced_at' => now(),
                ]);

                $locked->forceFill([
                    'data' => $validated['answers'],
                    'edited_at' => now(),
                ])->save();

                $storedFiles = $this->attachments->attach($locked, $validated['files']);

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->attachments->deleteFiles($storedFiles);

            throw $exception;
        }

        // Só depois do commit: se a transação falhasse, os arquivos antigos continuariam valendo.
        $replaced->each(fn (Media $media) => $media->delete());

        event(new SubmissionEdited($submission));

        return $submission;
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<string, int>
     */
    private function countByField(Collection $media): array
    {
        return $media->countBy(fn (Media $item): string => (string) $item->getCustomProperty('field_id'))->all();
    }
}
