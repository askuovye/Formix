<?php

declare(strict_types=1);

namespace App\Validation\Rules;

/**
 * Rejeita quando a resposta INTEIRA é um dos valores bloqueados, sem diferenciar
 * maiúsculas e acentos. Também ignora pontuação quando o item bloqueado tem
 * letras ou dígitos: com "teste" bloqueado, "Teste." e "TESTE!" também são
 * rejeitados. Nunca compara como substring: "Contestei a cobrança" passa.
 */
final class BlocklistRule extends HeuristicRule
{
    public function passes(string $value, array $params): bool
    {
        $folded = $this->normalizer->fold($value);
        $compact = $this->normalizer->compact($value);

        foreach ((array) $params['values'] as $blocked) {
            $blocked = (string) $blocked;
            $blockedCompact = $this->normalizer->compact($blocked);

            if ($folded === $this->normalizer->fold($this->normalizer->normalize($blocked))) {
                return false;
            }

            if ($blockedCompact !== '' && $compact === $blockedCompact) {
                return false;
            }
        }

        return true;
    }
}
