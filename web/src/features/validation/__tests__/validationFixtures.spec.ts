import { describe, expect, it } from 'vitest'

import fixtures from '@fixtures/validation-cases.json'
import type { InputField, Rule, RuleType, SchemaItem } from '@/types/schema'

import { validateAnswer, type AnswerableField } from '../answers'
import { normalize } from '../normalize'
import { RULES, ruleError } from '../rules'
import { hiddenFieldIds } from '../visibility'

/**
 * Contrato compartilhado com o back-end (fixtures/README.md): o espelho do
 * front precisa passar por todos os casos, como o Pest.
 */

interface FixtureField {
  type: string
  required?: boolean
  options?: string[]
  rating?: { max: number }
  scale?: { min: number; max: number }
}

/** Monta um campo completo a partir do resumo da fixture (`options` é a lista de valores). */
function fieldFrom(fixture: FixtureField): AnswerableField {
  return {
    id: 'campo',
    kind: 'input',
    label: 'Campo',
    ...fixture,
    options: (fixture.options ?? []).map((value) => ({ value, label: value })),
    scale: fixture.scale ? { ...fixture.scale, min_label: null, max_label: null } : undefined,
  } as AnswerableField
}

/** Itens da fixture de visibilidade: `kind` ausente = campo. */
function itemsFrom(fields: readonly Record<string, unknown>[]): SchemaItem[] {
  return fields.map((field) =>
    field.kind === 'block'
      ? (field as unknown as SchemaItem)
      : ({
          kind: 'input',
          label: String(field.id),
          ...field,
          options: ((field.options as string[] | undefined) ?? []).map((value) => ({
            value,
            label: value,
          })),
        } as unknown as InputField),
  )
}

describe('normalização', () => {
  it.each(fixtures.normalization.map((c) => [c.note, c] as const))('%s', (_note, c) => {
    expect(normalize(c.input, c.multiline)).toBe(c.output)
  })
})

describe('regras do catálogo', () => {
  const catalog = fixtures.rules as Record<
    string,
    { params: Record<string, unknown>; value: string; valid: boolean; multiline?: boolean }[]
  >

  it('toda regra do catálogo tem implementação e casos válidos e inválidos', () => {
    expect(Object.keys(catalog).sort()).toEqual(Object.keys(RULES).sort())
    for (const cases of Object.values(catalog)) {
      expect(cases.some((c) => c.valid)).toBe(true)
      expect(cases.some((c) => !c.valid)).toBe(true)
    }
  })

  const cases = Object.entries(catalog).flatMap(([type, list]) =>
    list.map((c) => [`${type}: ${JSON.stringify(c.value)}`, type as RuleType, c] as const),
  )

  it.each(cases)('%s', (_name, type, c) => {
    const rule = { type, ...c.params } as Rule
    const error = ruleError(normalize(c.value, c.multiline ?? false), rule)

    expect(error === null).toBe(c.valid)
  })

  it('usa a mensagem própria do dono quando existe', () => {
    expect(
      ruleError('aaaaa', { type: 'no_repeated_chars', threshold: 0.8, message: 'Nada de lixo' }),
    ).toBe('Nada de lixo')
  })
})

describe('respostas pelo tipo do campo', () => {
  it.each(fixtures.fields.map((c) => [c.note, c] as const))('%s', (_note, c) => {
    const result = validateAnswer(fieldFrom(c.field as FixtureField), c.value)

    // Válido: compara também a resposta normalizada; inválido: só o resultado.
    const outcome = result.valid ? result : { valid: false }
    const expected = c.valid
      ? { valid: true, value: 'normalized' in c ? c.normalized : undefined }
      : { valid: false }

    expect(outcome).toEqual(expected)
  })
})

describe('lógica condicional', () => {
  it.each(fixtures.visibility.map((c) => [c.note, c] as const))('%s', (_note, c) => {
    expect(
      hiddenFieldIds(itemsFrom(c.schema.fields as Record<string, unknown>[]), c.answers),
    ).toEqual(c.hidden)
  })
})
