<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Http\UploadedFile;

/**
 * Arquivo que passou na validação, com o tipo real detectado pelo conteúdo.
 */
final readonly class AcceptedFile
{
    public function __construct(
        public UploadedFile $file,
        public string $fieldId,
        public string $mimeType,
        public string $extension,
        // Nome original limpo (sem caminho, controle ou extensão enganosa) + extensão real.
        public string $displayName,
    ) {}
}
