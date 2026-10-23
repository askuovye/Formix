<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categorias de arquivo que o dono pode liberar num campo de upload.
 * Sem antivírus (D8), a lista é fechada: nada executável, HTML, SVG ou script.
 */
enum FileCategory: string
{
    case Pdf = 'pdf';
    case Image = 'image';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Text = 'text';

    /**
     * MIME real aceito (detectado pelo conteúdo) → extensão usada no disco e no download.
     * A extensão enviada pelo cliente nunca é usada: "foto.png.exe" com conteúdo PNG vira ".png".
     */
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'text/csv' => 'csv',
        'text/plain' => 'txt',
    ];

    /**
     * MIME reais aceitos (detectados pelo conteúdo, não pela extensão).
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['application/pdf'],
            self::Image => ['image/jpeg', 'image/png', 'image/webp'],
            self::Document => [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.text',
            ],
            self::Spreadsheet => [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'text/csv',
            ],
            self::Text => ['text/plain'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Image => 'imagem (JPG, PNG, WebP)',
            self::Document => 'documento (DOC, DOCX, ODT)',
            self::Spreadsheet => 'planilha (XLS, XLSX, ODS, CSV)',
            self::Text => 'texto (TXT)',
        };
    }

    public static function extensionFor(string $mimeType): string
    {
        return self::EXTENSIONS[$mimeType] ?? 'bin';
    }
}
