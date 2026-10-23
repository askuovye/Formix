<?php

declare(strict_types=1);

namespace App\Actions\Legal;

use App\Enums\LegalDocument;
use App\Models\User;
use App\Services\IpHasher;

/**
 * Registra o aceite da versão vigente de cada documento.
 */
final class RecordLegalAcceptance
{
    public function __construct(private readonly IpHasher $ipHasher) {}

    public function __invoke(User $user, ?string $ip): void
    {
        $ipHash = $this->ipHasher->hash($ip);
        $acceptedAt = now();

        foreach (LegalDocument::cases() as $document) {
            $user->legalAcceptances()->create([
                'document' => $document,
                'version' => $document->version(),
                'accepted_at' => $acceptedAt,
                'ip_hash' => $ipHash,
            ]);
        }
    }
}
