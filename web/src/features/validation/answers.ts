import * as v from 'valibot'

import { validation } from '@/strings'
import type { InputField, SchemaItem } from '@/types/schema'

import { length, normalize } from './normalize'
import { ruleError } from './rules'
import { hiddenFieldIds, type Answers } from './visibility'

/**
 * Validação das respostas pelo schema, espelho de
 * api/app/Validation/FormSubmissionValidator.php (casos em
 * fixtures/validation-cases.json → "fields"). Conveniência de UX: o back-end
 * revalida tudo e é a fonte da verdade.
 */

/** Espelho de RuleType::MAX_TEXT_LENGTH e FormSubmissionValidator::MAX_EMAIL_LENGTH. */
export const ANSWER_LIMITS = { textMax: 10_000, emailMax: 255 } as const

const NUMBER_PATTERN = /^-?\d{1,15}(\.\d{1,6})?$/
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/

const t = validation

/** Campo que gera resposta aqui (arquivos têm fluxo próprio). */
export type AnswerableField = Exclude<InputField, { type: 'file' }>

export function isAnswerable(item: SchemaItem): item is AnswerableField {
  return item.kind === 'input' && item.type !== 'file'
}

/**
 * Normaliza antes de validar; valores do tipo errado passam intactos para
 * falharem na regra de tipo. Vazio vira `null` (não respondido).
 */
export function prepareAnswer(field: AnswerableField, value: unknown): unknown {
  if (value === undefined) return null
  if (typeof value === 'string' && value.length > ANSWER_LIMITS.textMax * 4) return value

  const prepared = ((): unknown => {
    switch (field.type) {
      case 'short_text':
      case 'email':
        return typeof value === 'string' ? normalize(value) : value
      case 'long_text':
        return typeof value === 'string' ? normalize(value, true) : value
      case 'number':
        if (typeof value === 'number') return Number.isFinite(value) ? String(value) : 'invalido'
        return typeof value === 'string' ? value.trim().replace(/,/g, '.') : value
      case 'date':
      case 'single_choice':
        return typeof value === 'string' ? value.trim() : value
      case 'multiple_choice':
        return Array.isArray(value) && value.length === 0 ? null : value
      case 'rating':
      case 'scale':
        return typeof value === 'string' && /^-?\d{1,4}$/.test(value.trim())
          ? Number(value.trim())
          : value
    }
  })()

  return prepared === '' ? null : prepared
}

function isDate(value: string): boolean {
  if (!DATE_PATTERN.test(value)) return false

  const date = new Date(`${value}T00:00:00Z`)
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}

/** Email no formato usual (o back-end aplica a RFC completa). */
function isEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+$/.test(value) && !value.startsWith('.') && !/\.\.|@\.|\.@/.test(value)
}

function optionValues(field: { options: { value: string }[] }): string[] {
  return field.options.map((option) => option.value)
}

function integerBetween(min: number, max: number) {
  const message = t.integerRange(min, max)

  return v.pipe(
    v.number(message),
    v.integer(message),
    v.minValue(min, message),
    v.maxValue(max, message),
  )
}

/** Schema do valor já preparado e não vazio, pelo tipo do campo. */
function typeSchema(field: AnswerableField): v.GenericSchema<unknown> {
  switch (field.type) {
    case 'short_text':
    case 'long_text':
      return v.pipe(
        v.string(t.text),
        v.check(
          (value) => length(value) <= ANSWER_LIMITS.textMax,
          t.textMax(ANSWER_LIMITS.textMax),
        ),
        v.rawCheck(({ dataset, addIssue }) => {
          if (!dataset.typed) return
          const message = (field.rules ?? [])
            .map((rule) => ruleError(dataset.value, rule))
            .find((error) => error !== null)
          if (message) addIssue({ message })
        }),
      )
    case 'email':
      return v.pipe(
        v.string(t.email),
        v.check((value) => length(value) <= ANSWER_LIMITS.emailMax, t.email),
        v.check(isEmail, t.email),
      )
    case 'number':
      return v.pipe(v.string(t.number), v.regex(NUMBER_PATTERN, t.number))
    case 'date':
      return v.pipe(v.string(t.date), v.check(isDate, t.date))
    case 'single_choice':
      return v.pipe(v.string(t.choice), v.picklist(optionValues(field), t.choice))
    case 'multiple_choice': {
      const options = optionValues(field)
      return v.pipe(
        v.array(v.string(t.choices), t.choices),
        v.maxLength(options.length, t.choices),
        v.check((values) => values.every((value) => options.includes(value)), t.choices),
        v.check((values) => new Set(values).size === values.length, t.choices),
      )
    }
    case 'rating':
      return integerBetween(1, field.rating.max)
    case 'scale':
      return integerBetween(field.scale.min, field.scale.max)
  }
}

/** Schema Valibot da resposta de um campo: prepara, exige (se obrigatório) e valida o tipo e as regras. */
export function answerSchema(field: AnswerableField): v.GenericSchema<unknown> {
  const typed = typeSchema(field)

  return v.pipe(
    v.unknown(),
    v.transform((value) => prepareAnswer(field, value)),
    field.required ? v.nonNullable(typed, t.required) : v.nullable(typed),
  )
}

export type AnswerResult = { valid: true; value: unknown } | { valid: false; message: string }

/** Valida a resposta de um campo; para no primeiro erro, como o `bail` do back-end. */
export function validateAnswer(field: AnswerableField, value: unknown): AnswerResult {
  const result = v.safeParse(answerSchema(field), value, { abortPipeEarly: true })

  return result.success
    ? { valid: true, value: result.output }
    : { valid: false, message: result.issues[0].message }
}

export interface AnswersResult {
  /** Respostas normalizadas por id (`null` = não respondido ou oculto). */
  answers: Record<string, unknown>
  hidden: string[]
  /** Primeira mensagem de erro de cada campo visível. */
  errors: Record<string, string>
}

/**
 * Valida só o que o respondente vê: a visibilidade é calculada sobre as
 * respostas preparadas, e campo oculto não é validado e tem o valor descartado.
 */
export function validateAnswers(items: readonly SchemaItem[], input: Answers): AnswersResult {
  const fields = items.filter(isAnswerable)
  const prepared = Object.fromEntries(
    fields.map((field) => [field.id, prepareAnswer(field, input[field.id])]),
  )
  const hidden = hiddenFieldIds(items, prepared)
  const answers: Record<string, unknown> = {}
  const errors: Record<string, string> = {}

  for (const field of fields) {
    if (hidden.includes(field.id)) {
      answers[field.id] = null
      continue
    }

    const result = validateAnswer(field, input[field.id])
    if (result.valid) answers[field.id] = result.value
    else errors[field.id] = result.message
  }

  return { answers, hidden, errors }
}
