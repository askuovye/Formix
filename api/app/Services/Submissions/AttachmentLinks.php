<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Links assinados de download e a entrega segura do arquivo.
 *
 * O link usa o UUID do anexo (não o ID sequencial da tabela `media`).
 */
final class AttachmentLinks
{
    public const ROUTE = 'files.download';

    public function temporaryUrl(Media $media): string
    {
        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addDays((int) config('submissions.attachment_link_ttl_days')),
            ['media' => $media->uuid],
        );
    }

    /**
     * Sem antivírus (D8), a entrega é o que protege quem baixa:
     *  - sempre `attachment` (o navegador baixa, nunca abre/renderiza);
     *  - Content-Type = tipo real detectado no upload, com `nosniff`;
     *  - CSP `sandbox` caso algum navegador decida exibir mesmo assim;
     *  - sem cache em proxies.
     */
    public function download(string $uuid): StreamedResponse
    {
        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('model_type', (new Submission)->getMorphClass())
            ->first();

        // Resposta ou formulário excluídos: o link deixa de funcionar.
        $submission = $media?->model;

        if ($media === null || ! $submission instanceof Submission || $submission->form === null) {
            throw new NotFoundHttpException;
        }

        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $media->name, [
            'Content-Type' => (string) $media->getCustomProperty('detected_mime_type', $media->mime_type),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
