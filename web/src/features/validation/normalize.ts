/**
 * Espelho de api/app/Validation/InputNormalizer.php, na mesma ordem
 * (casos em fixtures/validation-cases.json → "normalization"):
 *
 *  1. quebras de linha `\r\n` e `\r` viram `\n`;
 *  2. Unicode NFC;
 *  3. remove caracteres invisíveis (zero-width, BOM, soft hyphen) e de controle, exceto `\n` e `\t`;
 *  4. espaços repetidos viram um; texto de uma linha também transforma `\n` em espaço;
 *  5. várias linhas: apara cada linha e limita a uma linha em branco seguida;
 *  6. apara o início e o fim.
 */

// Zero-width space/joiner/non-joiner, word joiner, BOM, soft hyphen e controles C0/C1 (exceto \t e \n).
const INVISIBLE_CHARACTERS =
  /[\u{200B}-\u{200D}\u{2060}\u{FEFF}\u{AD}\u{0}-\u{8}\u{B}-\u{1F}\u{7F}-\u{9F}]/gu

export function normalize(value: string, multiline = false): string {
  const cleaned = value.replace(/\r\n?/g, '\n').normalize('NFC').replace(INVISIBLE_CHARACTERS, '')

  if (!multiline) {
    return cleaned.replace(/\s+/gu, ' ').trim()
  }

  return cleaned
    .split('\n')
    .map((line) => line.replace(/[^\S\n]+/gu, ' ').trim())
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/** Minúsculas e sem acentos: "Ação" e "acao" são a mesma coisa para as heurísticas. */
export function fold(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
}

/** Só letras e dígitos da forma dobrada: "1.2.3-4" e "1234" ficam iguais. */
export function compact(value: string): string {
  return fold(value).replace(/[^\p{L}\p{N}]/gu, '')
}

/** Tamanho em caracteres Unicode (code points), como `mb_strlen`. */
export function length(value: string): number {
  return [...value].length
}
