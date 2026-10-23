<?php

declare(strict_types=1);

namespace App\Validation\Rules;

/**
 * Rejeita quando um único caractere ocupa ≥ `threshold` dos caracteres
 * (sem espaços, forma dobrada): "aaaaa", "0000", "....", "kkkkkkk".
 * Respostas com menos de 3 caracteres não são julgadas: "Ok" ou "Lu" não são lixo.
 */
final class NoRepeatedCharsRule extends HeuristicRule
{
    public const MIN_LENGTH_TO_JUDGE = 3;

    public function passes(string $value, array $params): bool
    {
        $characters = mb_str_split((string) preg_replace('/\s/u', '', $this->normalizer->fold($value)));
        $length = count($characters);

        if ($length < self::MIN_LENGTH_TO_JUDGE) {
            return true;
        }

        $mostFrequent = max(array_count_values($characters));

        return $mostFrequent / $length < (float) $params['threshold'];
    }
}
