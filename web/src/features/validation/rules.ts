import { validation } from '@/strings'
import type { Rule, RuleType } from '@/types/schema'

import { compact, fold, length, normalize } from './normalize'

/**
 * Regras do catálogo, espelho de api/app/Validation/Rules (casos em
 * fixtures/validation-cases.json → "rules"). Recebem o texto já normalizado
 * e nunca vazio (vazio é tratado por `required`, antes).
 */
interface RuleImplementation<T extends RuleType> {
  passes: (value: string, rule: RuleOf<T>) => boolean
  message: (rule: RuleOf<T>) => string
}

type RuleOf<T extends RuleType> = Rule & { type: T }

type RuleCatalog = { [T in RuleType]: RuleImplementation<T> }

const t = validation.rules

const heuristic = (): string => t.heuristic

/** Caracteres sem espaços, na forma dobrada. */
function foldedCharacters(value: string): string[] {
  return [...fold(value).replace(/\s/gu, '')]
}

function allSame(digits: string): boolean {
  return new Set(digits).size === 1
}

// no_sequences
const DEFAULT_SEQUENCE_LENGTH = 4
const KEYBOARD_ROWS = ['1234567890', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm']
const MIN_UNIT_REPETITIONS = 3

function isStepRun(value: string): boolean {
  // Só ASCII: letras acentuadas já foram dobradas; outros alfabetos não formam sequência.
  if (!/^[a-z0-9]+$/.test(value) || value.length < 2) return false

  const step = value.charCodeAt(1) - value.charCodeAt(0)
  if (Math.abs(step) !== 1) return false

  for (let i = 2; i < value.length; i++) {
    if (value.charCodeAt(i) - value.charCodeAt(i - 1) !== step) return false
  }

  return true
}

function isKeyboardRun(value: string): boolean {
  return KEYBOARD_ROWS.some(
    (row) => row.includes(value) || [...row].reverse().join('').includes(value),
  )
}

function isRepeatedUnit(value: string): boolean {
  const characters = [...value]

  return [2, 3].some((unitLength) => {
    const repetitions = characters.length / unitLength
    if (!Number.isInteger(repetitions) || repetitions < MIN_UNIT_REPETITIONS) return false

    return characters.slice(0, unitLength).join('').repeat(repetitions) === value
  })
}

// Documentos: dígitos verificadores como no LaravelLegends/pt-br-validator.
function checkDigit(sum: number): number {
  const rest = sum % 11
  return rest < 2 ? 0 : 11 - rest
}

function isCpf(value: string): boolean {
  if (!/^[\d.\-\s]+$/.test(value)) return false

  const digits = value.replace(/\D/g, '')
  if (digits.length !== 11 || allSame(digits)) return false

  const numbers = [...digits].map(Number)
  const sum = (count: number): number =>
    numbers.slice(0, count).reduce((total, digit, i) => total + digit * (count + 1 - i), 0)

  return numbers[9] === checkDigit(sum(9)) && numbers[10] === checkDigit(sum(10))
}

/** Aceita o CNPJ alfanumérico: cada caractere vale o código ASCII menos 48. */
function isCnpj(value: string): boolean {
  if (!/^[0-9A-Za-z./\-\s]+$/.test(value)) return false

  const characters = value.toUpperCase().replace(/[^0-9A-Z]/g, '')
  if (characters.length !== 14 || allSame(characters)) return false

  const weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const values = [...characters].map((character) => character.charCodeAt(0) - 48)
  const sum = (count: number, offset: number): number =>
    values.slice(0, count).reduce((total, n, i) => total + n * (weights[i + offset] ?? 0), 0)

  return values[12] === checkDigit(sum(12, 1)) && values[13] === checkDigit(sum(13, 0))
}

const COUNTRY_CODE = '55'

function isPhoneBr(value: string): boolean {
  if (!/^[\d\s()+\-.]+$/.test(value)) return false

  let digits = value.replace(/\D/g, '')
  if ((digits.length === 12 || digits.length === 13) && digits.startsWith(COUNTRY_CODE)) {
    digits = digits.slice(COUNTRY_CODE.length)
  }

  return (
    (digits.length === 10 || digits.length === 11) &&
    !digits.startsWith('0') &&
    (digits.length === 10 || digits[2] === '9') &&
    !allSame(digits)
  )
}

function isCep(value: string): boolean {
  if (!/^[\d.\-\s]+$/.test(value)) return false

  const digits = value.replace(/\D/g, '')
  return digits.length === 8 && !allSame(digits)
}

export const RULES: RuleCatalog = {
  min_length: {
    passes: (value, rule) => length(value) >= rule.value,
    message: (rule) => t.minLength(rule.value),
  },
  max_length: {
    passes: (value, rule) => length(value) <= rule.value,
    message: (rule) => t.maxLength(rule.value),
  },
  min_words: {
    // Palavra = trecho entre espaços com ao menos uma letra ou dígito.
    passes: (value, rule) =>
      value.split(/\s+/u).filter((token) => /[\p{L}\p{N}]/u.test(token)).length >= rule.value,
    message: (rule) => t.minWords(rule.value),
  },
  min_distinct_chars: {
    passes: (value, rule) => new Set(foldedCharacters(value)).size >= rule.value,
    message: heuristic,
  },
  no_repeated_chars: {
    // Respostas com menos de 3 caracteres não são julgadas ("Ok", "Lu").
    passes: (value, rule) => {
      const characters = foldedCharacters(value)
      if (characters.length < 3) return true

      const counts = new Map<string, number>()
      for (const character of characters) counts.set(character, (counts.get(character) ?? 0) + 1)

      return Math.max(...counts.values()) / characters.length < rule.threshold
    },
    message: heuristic,
  },
  no_sequences: {
    // Só a resposta inteira, nunca um trecho: "Rua Abc, 1234" é legítimo.
    passes: (value, rule) => {
      const compacted = compact(value)
      const minLength = rule.value ?? DEFAULT_SEQUENCE_LENGTH

      if (compacted.length >= minLength && (isStepRun(compacted) || isKeyboardRun(compacted))) {
        return false
      }

      return !isRepeatedUnit(compacted)
    },
    message: heuristic,
  },
  no_only_symbols: {
    passes: (value) => /[\p{L}\p{N}]/u.test(value),
    message: heuristic,
  },
  blocklist: {
    // Resposta inteira, sem maiúsculas e acentos; ignora pontuação quando o item tem letras.
    passes: (value, rule) => {
      const folded = fold(value)
      const compacted = compact(value)

      return rule.values.every((blocked) => {
        const blockedCompact = compact(blocked)

        return (
          folded !== fold(normalize(blocked)) &&
          (blockedCompact === '' || compacted !== blockedCompact)
        )
      })
    },
    message: heuristic,
  },
  cpf: { passes: isCpf, message: () => t.cpf },
  cnpj: { passes: isCnpj, message: () => t.cnpj },
  phone_br: { passes: isPhoneBr, message: () => t.phoneBr },
  cep: { passes: isCep, message: () => t.cep },
}

/** Aplica a regra; devolve a mensagem de erro (a própria do dono, se houver) ou `null`. */
export function ruleError(value: string, rule: Rule): string | null {
  const implementation = RULES[rule.type] as RuleImplementation<RuleType>

  return implementation.passes(value, rule as never)
    ? null
    : (rule.message ?? implementation.message(rule as never))
}
