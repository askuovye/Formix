<?php

declare(strict_types=1);

namespace App\Validation;

use Normalizer;

/**
 * Normalização aplicada a toda resposta de texto ANTES das regras, e o valor
 * normalizado é o que fica gravado. O espelho TypeScript deve fazer o mesmo,
 * na mesma ordem (casos em fixtures/validation-cases.json → "normalization"):
 *
 *  1. quebras de linha `\r\n` e `\r` viram `\n`;
 *  2. Unicode NFC (letra + acento combinante vira um caractere só);
 *  3. remove caracteres invisíveis (zero-width, BOM, soft hyphen) e de controle,
 *     exceto `\n` e `\t`: impedem burlar as regras com "a​a​a";
 *  4. tabs e espaços repetidos viram um espaço; texto de uma linha também
 *     transforma `\n` em espaço;
 *  5. em texto de várias linhas: apara cada linha e limita a uma linha em branco seguida;
 *  6. apara o início e o fim.
 *
 * As regras heurísticas comparam uma forma "dobrada" (minúsculas, sem acentos),
 * e algumas uma forma "compacta" (só letras e dígitos da forma dobrada).
 */
final class InputNormalizer
{
    // Zero-width space/joiner/non-joiner, word joiner, BOM, soft hyphen e controles C0/C1 (exceto \t e \n).
    private const INVISIBLE_CHARACTERS = '/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u';

    public function normalize(string $value, bool $multiline = false): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        $value = (string) preg_replace(self::INVISIBLE_CHARACTERS, '', $value);

        if (! $multiline) {
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        }

        $lines = array_map(
            fn (string $line): string => trim((string) preg_replace('/[^\S\n]+/u', ' ', $line)),
            explode("\n", $value),
        );

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * Minúsculas e sem acentos: "Ação" e "acao" são a mesma coisa para as heurísticas.
     */
    public function fold(string $value): string
    {
        $decomposed = Normalizer::normalize(mb_strtolower($value), Normalizer::FORM_D) ?: $value;

        return (string) preg_replace('/\p{Mn}/u', '', $decomposed);
    }

    /**
     * Só letras e dígitos da forma dobrada: "1.2.3-4" e "1234" ficam iguais.
     */
    public function compact(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', $this->fold($value));
    }
}
