<?php

declare(strict_types=1);

namespace App\Validation\Rules;

/**
 * Exige ao menos `value` caracteres diferentes (sem espaços, forma dobrada,
 * então "A" e "á" contam como o mesmo).
 */
final class MinDistinctCharsRule extends HeuristicRule
{
    public function passes(string $value, array $params): bool
    {
        $characters = mb_str_split((string) preg_replace('/\s/u', '', $this->normalizer->fold($value)));

        return count(array_unique($characters)) >= (int) $params['value'];
    }
}
