<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use Illuminate\Support\Str;

/**
 * Token do link de edição do respondente. No banco fica só o SHA-256: o token
 * é aleatório (≈ 285 bits), então um hash rápido basta e um vazamento do banco
 * não dá acesso a nenhuma resposta.
 */
final class EditTokens
{
    private const TOKEN_LENGTH = 48;

    public function generate(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    public function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function matches(#[\SensitiveParameter] string $token, ?string $hash): bool
    {
        return $hash !== null && $token !== '' && hash_equals($hash, $this->hash($token));
    }
}
