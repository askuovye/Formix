import { PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH } from './schemas'

/** 0 = vazia, 1 = fraca, 2 = razoável, 3 = boa, 4 = forte. */
export type PasswordStrength = 0 | 1 | 2 | 3 | 4

/** Trechos comuns que tornam a senha fácil de adivinhar. */
const COMMON = ['senha', 'password', 'qwerty', 'asdf', 'admin', 'abc123', '123456', 'mudar']

/** Algum caractere ocupa metade ou mais da senha (`aaaaaaa1`). */
function isRepetitive(password: string): boolean {
  const counts = new Map<string, number>()

  for (const char of password) {
    counts.set(char, (counts.get(char) ?? 0) + 1)
  }

  return Math.max(...counts.values()) * 2 >= password.length
}

/** A maior parte é uma sequência crescente ou decrescente (`12345678a`, `abcdefg1`). */
function isSequential(password: string): boolean {
  let longest = 1
  let run = 1
  let step = 0

  for (let i = 1; i < password.length; i++) {
    const diff = password.charCodeAt(i) - password.charCodeAt(i - 1)

    if ((diff === 1 || diff === -1) && (run === 1 || diff === step)) {
      step = diff
      run++
    } else {
      run = diff === 1 || diff === -1 ? 2 : 1
      step = diff
    }
    longest = Math.max(longest, run)
  }

  return longest * 10 >= password.length * 6
}

/**
 * Força estimada da senha, só para orientar quem digita (a barra do cadastro).
 * Não valida nada: as regras obrigatórias estão no schema e no back-end.
 */
export function passwordStrength(password: string): PasswordStrength {
  if (password === '') {
    return 0
  }

  const meetsRules =
    password.length >= PASSWORD_MIN_LENGTH &&
    password.length <= PASSWORD_MAX_LENGTH &&
    /\p{L}/u.test(password) &&
    /\p{N}/u.test(password)
  const lower = password.toLowerCase()
  const predictable =
    isRepetitive(password) || isSequential(lower) || COMMON.some((word) => lower.includes(word))

  if (!meetsRules || predictable) {
    return 1
  }

  const points =
    Number(password.length >= 12) +
    Number(password.length >= 16) +
    Number(/\p{Lu}/u.test(password) && /\p{Ll}/u.test(password)) +
    Number(/[^\p{L}\p{N}]/u.test(password))

  if (points >= 3) {
    return 4
  }

  return points >= 1 ? 3 : 2
}
