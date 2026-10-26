import type { InputType, SchemaItem, VisibilityOperator } from '@/types/schema'

import { fold, normalize } from './normalize'

/**
 * Lógica condicional: quais campos estão ocultos para estas respostas.
 * Espelho de api/app/Validation/VisibilityEvaluator.php (casos em
 * fixtures/validation-cases.json → "visibility"):
 *  - percorre os campos na ordem; a origem sempre vem antes, então uma passada resolve cadeias;
 *  - origem oculta conta como não respondida;
 *  - "não respondido" = null, texto só com espaços ou lista vazia;
 *  - comparações (eq, lt, includes…) com origem não respondida são falsas;
 *  - texto compara sem maiúsculas e acentos; número aceita vírgula; data compara `AAAA-MM-DD`;
 *    valor que não é do tipo esperado torna a condição falsa.
 */

/** Respostas por id de campo, como o respondente preencheu. */
export type Answers = Readonly<Record<string, unknown>>

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const NUMERIC_TYPES: InputType[] = ['number', 'rating', 'scale']

export function isAnswered(value: unknown): boolean {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') return value.trim() !== ''
  if (Array.isArray(value)) return value.length > 0

  return true
}

function toNumber(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value !== 'string') return null

  // Mesmo critério do is_numeric do PHP para os casos que importam aqui.
  const normalized = value.trim().replace(/,/g, '.')
  return /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(normalized) ? Number(normalized) : null
}

function toDate(value: unknown): string | null {
  return typeof value === 'string' && DATE_PATTERN.test(value.trim()) ? value.trim() : null
}

function isScalar(value: unknown): value is string | number | boolean {
  return ['string', 'number', 'boolean'].includes(typeof value)
}

function compare<T extends number | string>(
  operator: VisibilityOperator,
  left: T | null,
  right: T | null,
): boolean {
  if (left === null || right === null) return false

  const order = left < right ? -1 : left > right ? 1 : 0
  switch (operator) {
    case 'eq':
      return order === 0
    case 'neq':
      return order !== 0
    case 'lt':
      return order < 0
    case 'lte':
      return order <= 0
    case 'gt':
      return order > 0
    case 'gte':
      return order >= 0
    default:
      return false
  }
}

function includes(operator: VisibilityOperator, value: unknown, expected: unknown): boolean {
  if (!Array.isArray(value) || !isScalar(expected)) return false

  const contains = value.some((item) => isScalar(item) && String(item) === String(expected))
  if (operator === 'includes') return contains
  if (operator === 'excludes') return !contains

  return false
}

function equalsText(operator: VisibilityOperator, value: unknown, expected: unknown): boolean {
  if (!isScalar(value) || !isScalar(expected)) return false

  const same = fold(normalize(String(value))) === fold(normalize(String(expected)))
  if (operator === 'eq') return same
  if (operator === 'neq') return !same

  return false
}

function matches(
  type: InputType,
  operator: VisibilityOperator,
  value: unknown,
  expected: unknown,
): boolean {
  const answered = isAnswered(value)

  if (operator === 'answered' || operator === 'not_answered') {
    return answered === (operator === 'answered')
  }
  if (!answered) return false

  if (NUMERIC_TYPES.includes(type)) return compare(operator, toNumber(value), toNumber(expected))
  if (type === 'date') return compare(operator, toDate(value), toDate(expected))
  if (type === 'multiple_choice') return includes(operator, value, expected)

  return equalsText(operator, value, expected)
}

/** Ids dos campos ocultos, na ordem do schema. Blocos são ignorados. */
export function hiddenFieldIds(items: readonly SchemaItem[], answers: Answers): string[] {
  const types = new Map<string, InputType>()
  const hidden = new Set<string>()

  for (const item of items) {
    if (item.kind !== 'input') continue

    types.set(item.id, item.type)
    const visibility = item.visibility
    if (!visibility) continue

    const { field, operator, value } = visibility.when
    const sourceType = types.get(field)
    const sourceValue = hidden.has(field) ? null : answers[field]
    const met = sourceType !== undefined && matches(sourceType, operator, sourceValue, value)

    if (visibility.action === 'show' ? !met : met) hidden.add(item.id)
  }

  return [...hidden]
}
