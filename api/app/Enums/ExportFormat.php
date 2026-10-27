<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Pdf = 'pdf';

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Pdf => 'application/pdf',
        };
    }
}
