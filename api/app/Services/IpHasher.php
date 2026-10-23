<?php

declare(strict_types=1);

namespace App\Services;

/**
 * LGPD: o IP nunca é armazenado puro (AGENTS.md §4). O HMAC com chave secreta
 * permite comparar IPs (prevenção de abuso) sem que um vazamento do banco
 * revele o IP, já que o espaço de IPv4 é pequeno o bastante para força bruta
 * contra um hash sem chave.
 */
final class IpHasher
{
    public function hash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, $this->key());
    }

    private function key(): string
    {
        $salt = (string) config('app.ip_hash_salt');

        // Sem sal dedicado, deriva uma chave da APP_KEY (nunca a usa diretamente).
        return $salt !== '' ? $salt : hash_hmac('sha256', 'ip-hash', (string) config('app.key'));
    }
}
