import { builder } from '@/strings'
import type { InputField, SchemaItem, Visibility, VisibilityOperator } from '@/types/schema'

/** Espelho de FormSchemaRules (back-end). */
export const VISIBILITY_LIMITS = {
  /** Campos com condição por formulário. */
  maxRules: 50,
  textMax: 200,
} as const

/** Tipo do valor comparado, pelo tipo do campo de origem. */
export type ConditionValueKind = 'number' | 'date' | 'option' | 'text'

const COMPARISON: VisibilityOperator[] = ['eq', 'neq', 'lt', 'lte', 'gt', 'gte']
const PRESENCE: VisibilityOperator[] = ['answered', 'not_answered']

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/

const t = builder.logic

/** Operadores válidos para o tipo do campo de origem (VisibilityOperator::forSource). */
export function operatorsFor(source: InputField): VisibilityOperator[] {
  switch (source.type) {
    case 'number':
    case 'rating':
    case 'scale':
    case 'date':
      return [...COMPARISON, ...PRESENCE]
    case 'multiple_choice':
      return ['includes', 'excludes', ...PRESENCE]
    case 'file':
      return []
    default:
      return ['eq', 'neq', ...PRESENCE]
  }
}

export function needsValue(operator: VisibilityOperator): boolean {
  return !PRESENCE.includes(operator)
}

export function valueKindOf(source: InputField): ConditionValueKind {
  switch (source.type) {
    case 'number':
    case 'rating':
    case 'scale':
      return 'number'
    case 'date':
      return 'date'
    case 'single_choice':
    case 'multiple_choice':
      return 'option'
    default:
      return 'text'
  }
}

/** Campos que podem ser origem da condição do item na posição `index`: os anteriores, sem arquivo. */
export function conditionSources(items: readonly SchemaItem[], index: number): InputField[] {
  return items
    .slice(0, Math.max(index, 0))
    .flatMap((item) => (item.kind === 'input' && item.type !== 'file' ? [item] : []))
}

/** Condição inicial ao ligar a lógica: primeiro campo disponível, primeiro operador. */
export function defaultVisibility(source: InputField): Visibility {
  return { action: 'show', when: defaultWhen(source) }
}

/** Ao trocar o campo de origem, operador e valor voltam ao padrão desse campo. */
export function defaultWhen(source: InputField): Visibility['when'] {
  const operator = operatorsFor(source)[0] ?? 'answered'
  const value = defaultValue(source, operator)

  return value === undefined
    ? { field: source.id, operator }
    : { field: source.id, operator, value }
}

/** Valor padrão ao escolher um operador: a primeira opção (escolha); vazio nos demais. */
export function defaultValue(
  source: InputField,
  operator: VisibilityOperator,
): string | number | undefined {
  if (!needsValue(operator)) return undefined
  if (source.type === 'single_choice' || source.type === 'multiple_choice') {
    return source.options[0]?.value
  }

  return undefined
}

function valueIsValid(source: InputField, value: unknown): boolean {
  switch (valueKindOf(source)) {
    case 'number':
      return typeof value === 'number' && Number.isFinite(value)
    case 'date':
      return typeof value === 'string' && DATE_PATTERN.test(value)
    case 'option':
      return (
        typeof value === 'string' &&
        'options' in source &&
        source.options.some((option) => option.value === value)
      )
    default:
      return (
        typeof value === 'string' &&
        value.trim() !== '' &&
        value.length <= VISIBILITY_LIMITS.textMax
      )
  }
}

/** Erro da condição do item na posição `index` (mesmas regras do back-end), ou `null`. */
export function visibilityError(items: readonly SchemaItem[], index: number): string | null {
  const item = items[index]
  if (!item || item.kind !== 'input' || !item.visibility) return null

  const { when } = item.visibility
  const sourceIndex = items.findIndex((candidate) => candidate.id === when.field)
  const source = items[sourceIndex]

  if (sourceIndex < 0 || !source) return t.errors.missingSource
  if (sourceIndex >= index) return t.errors.sourceAfter
  if (source.kind !== 'input' || operatorsFor(source).length === 0) return t.errors.badSource
  if (!operatorsFor(source).includes(when.operator)) return t.errors.badOperator
  if (!needsValue(when.operator)) return null
  if (when.value === undefined || when.value === '') return t.errors.valueRequired

  return valueIsValid(source, when.value) ? null : t.errors.badValue
}

/** Quantos campos têm condição (o servidor aceita até VISIBILITY_LIMITS.maxRules). */
export function countConditions(items: readonly SchemaItem[]): number {
  return items.filter((item) => item.kind === 'input' && item.visibility).length
}

/** Rótulo de um campo para frases: o texto da pergunta ou o padrão. */
function nameOf(item: SchemaItem | undefined): string {
  return item?.kind === 'input' && item.label.trim() !== '' ? item.label : t.unnamed
}

function valueLabel(source: SchemaItem | undefined, value: string | number | undefined): string {
  if (value === undefined || value === '') return t.noValue
  if (source?.kind === 'input' && 'options' in source) {
    return source.options.find((option) => option.value === value)?.label ?? String(value)
  }
  if (typeof value === 'string' && DATE_PATTERN.test(value)) {
    const [year, month, day] = value.split('-')
    return `${day}/${month}/${year}`
  }

  return String(value)
}

/** Frase da condição: Mostrar quando "Nota" for menor ou igual a 2. */
export function describeVisibility(visibility: Visibility, items: readonly SchemaItem[]): string {
  const source = items.find((item) => item.id === visibility.when.field)
  const operator = t.operators[visibility.when.operator]
  const condition = needsValue(visibility.when.operator)
    ? `${operator} ${valueLabel(source, visibility.when.value)}`
    : operator

  return t.sentence(t.actions[visibility.action], nameOf(source), condition)
}
