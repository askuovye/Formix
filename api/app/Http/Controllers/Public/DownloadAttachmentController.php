<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Submissions\AttachmentLinks;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download de anexo por link assinado e temporário (middleware `signed`).
 * O link sai nos emails ao dono e, no painel, é gerado para o dono logado.
 */
final class DownloadAttachmentController extends Controller
{
    public function __invoke(string $media, AttachmentLinks $links): StreamedResponse
    {
        return $links->download($media);
    }
}
