<?php

declare(strict_types=1);

namespace App\Data;

final readonly class FileValidationResult
{
    /**
     * @param  array<string, list<AcceptedFile>>  $accepted  por id de campo; só campos que receberam arquivos
     * @param  array<string, list<string>>  $errors  por id de campo (ou `files` para o limite total)
     */
    public function __construct(
        public array $accepted,
        public array $errors,
    ) {}
}
