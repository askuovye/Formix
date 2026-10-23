<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Data\AcceptedFile;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Grava os anexos de uma resposta no disco privado (MEDIA_DISK), sem processar
 * o conteúdo (nenhuma conversão de imagem: o arquivo não é confiável).
 *
 * No disco: nome gerado (UUID) + extensão do tipo real. O nome original limpo
 * fica só como `name` (exibição e download).
 */
final class AttachmentStorage
{
    /**
     * @param  array<string, list<AcceptedFile>>  $filesByField
     * @return list<Media>
     */
    public function attach(Submission $submission, array $filesByField): array
    {
        $stored = [];

        try {
            foreach ($filesByField as $fieldId => $files) {
                foreach ($files as $accepted) {
                    $stored[] = $this->store($submission, $fieldId, $accepted);
                }
            }
        } catch (Throwable $exception) {
            // Arquivo gravado não volta atrás com o rollback da transação: apaga na mão.
            $this->deleteFiles($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * Remove os arquivos do disco (usado quando a transação que os criou falha).
     *
     * @param  list<Media>  $media
     */
    public function deleteFiles(array $media): void
    {
        foreach ($media as $item) {
            Storage::disk($item->disk)->deleteDirectory(dirname($item->getPathRelativeToRoot()));
        }
    }

    private function store(Submission $submission, string $fieldId, AcceptedFile $accepted): Media
    {
        return $submission
            ->addMedia($accepted->file)
            ->usingFileName(Str::uuid()->toString().'.'.$accepted->extension)
            ->usingName($accepted->displayName)
            ->withCustomProperties([
                'field_id' => $fieldId,
                'detected_mime_type' => $accepted->mimeType,
            ])
            ->toMediaCollection(Submission::mediaCollectionFor($fieldId), (string) config('media-library.disk_name'));
    }
}
