<?php

declare(strict_types=1);

namespace App\Validation\Rules;

/**
 * Rejeita quando a resposta INTEIRA (forma compacta: só letras e dígitos) é:
 *  - uma sequência crescente ou decrescente de ≥ `value` caracteres (padrão 4): "12345", "abcde", "9876";
 *  - um trecho de ≥ `value` caracteres de uma fileira do teclado, em qualquer sentido: "qwerty", "asdf", "7890";
 *  - um bloco de 2 ou 3 caracteres repetido 3+ vezes: "121212", "abcabcabc", "hahaha".
 *
 * Só a resposta inteira, nunca um trecho: "Rua Abc, 1234" ou um telefone com "1234" são legítimos.
 */
final class NoSequencesRule extends HeuristicRule
{
    public const DEFAULT_MIN_LENGTH = 4;

    private const KEYBOARD_ROWS = ['1234567890', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

    private const MIN_UNIT_REPETITIONS = 3;

    public function passes(string $value, array $params): bool
    {
        $compact = $this->normalizer->compact($value);
        $minLength = (int) ($params['value'] ?? self::DEFAULT_MIN_LENGTH);

        if (strlen($compact) >= $minLength && ($this->isStepRun($compact) || $this->isKeyboardRun($compact))) {
            return false;
        }

        return ! $this->isRepeatedUnit($compact);
    }

    private function isStepRun(string $value): bool
    {
        // Só ASCII: letras acentuadas já foram dobradas; outros alfabetos não formam sequência.
        if (preg_match('/^[a-z0-9]+$/', $value) !== 1) {
            return false;
        }

        $step = ord($value[1]) - ord($value[0]);

        if (abs($step) !== 1) {
            return false;
        }

        for ($i = 2, $length = strlen($value); $i < $length; $i++) {
            if (ord($value[$i]) - ord($value[$i - 1]) !== $step) {
                return false;
            }
        }

        return true;
    }

    private function isKeyboardRun(string $value): bool
    {
        foreach (self::KEYBOARD_ROWS as $row) {
            if (str_contains($row, $value) || str_contains(strrev($row), $value)) {
                return true;
            }
        }

        return false;
    }

    private function isRepeatedUnit(string $value): bool
    {
        $characters = mb_str_split($value);
        $length = count($characters);

        foreach ([2, 3] as $unitLength) {
            if ($length % $unitLength !== 0 || $length / $unitLength < self::MIN_UNIT_REPETITIONS) {
                continue;
            }

            $unit = implode('', array_slice($characters, 0, $unitLength));

            if (str_repeat($unit, intdiv($length, $unitLength)) === $value) {
                return true;
            }
        }

        return false;
    }
}
