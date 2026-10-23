<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Um envio de resposta como chegou da rota pública, já separado do HTTP.
 */
final readonly class SubmissionAttempt
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $files  arquivos por id de campo (UploadedFile ou lista)
     */
    public function __construct(
        public array $answers,
        public array $files,
        public bool $honeypotFilled,
        public ?string $idempotencyKey,
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
