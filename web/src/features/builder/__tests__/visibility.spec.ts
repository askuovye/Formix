import { describe, expect, it } from 'vitest'

import type { InputField, SchemaItem, Visibility } from '@/types/schema'

import { GENERAL_ERRORS, validateItems } from '../schemaErrors'
import {
  conditionSources,
  defaultVisibility,
  describeVisibility,
  operatorsFor,
  visibilityError,
} from '../visibility'

const nota: InputField = {
  id: 'nota',
  kind: 'input',
  type: 'rating',
  label: 'Nota',
  rating: { max: 5 },
}
const cor: InputField = {
  id: 'cor',
  kind: 'input',
  type: 'single_choice',
  label: 'Cor',
  options: [
    { value: 'azul', label: 'Azul' },
    { value: 'verde', label: 'Verde' },
  ],
}
const extras: InputField = {
  id: 'extras',
  kind: 'input',
  type: 'multiple_choice',
  label: 'Extras',
  options: [{ value: 'cafe', label: 'Café' }],
}
const nascimento: InputField = {
  id: 'nascimento',
  kind: 'input',
  type: 'date',
  label: 'Nascimento',
}
const nome: InputField = { id: 'nome', kind: 'input', type: 'short_text', label: 'Nome' }
const doc: InputField = {
  id: 'doc',
  kind: 'input',
  type: 'file',
  label: 'Doc',
  file: { accept: ['pdf'], max_files: 1, max_size_mb: 5 },
}

function target(visibility: Visibility): InputField {
  return { id: 'motivo', kind: 'input', type: 'long_text', label: 'Motivo', visibility }
}

function errorOf(items: SchemaItem[]): string | null {
  return visibilityError(items, items.length - 1)
}

describe('operadores por tipo de origem (VisibilityOperator::forSource)', () => {
  it.each<[InputField, string[]]>([
    [nota, ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'answered', 'not_answered']],
    [nascimento, ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'answered', 'not_answered']],
    [cor, ['eq', 'neq', 'answered', 'not_answered']],
    [extras, ['includes', 'excludes', 'answered', 'not_answered']],
    [nome, ['eq', 'neq', 'answered', 'not_answered']],
    [doc, []],
  ])('%o', (source, expected) => {
    expect(operatorsFor(source)).toEqual(expected)
  })
})

describe('origens possíveis', () => {
  it('só campos anteriores, sem arquivo nem blocos', () => {
    const items: SchemaItem[] = [
      { id: 'titulo', kind: 'block', type: 'section_title', text: 'Oi' },
      doc,
      nota,
      cor,
    ]

    expect(conditionSources(items, 3).map((item) => item.id)).toEqual(['nota'])
  })

  it('condição inicial de escolha já vem com a primeira opção', () => {
    expect(defaultVisibility(cor)).toEqual({
      action: 'show',
      when: { field: 'cor', operator: 'eq', value: 'azul' },
    })
  })
})

describe('visibilityError (mesmas regras do back-end)', () => {
  it.each<[string, SchemaItem[], string | null]>([
    [
      'válida (número)',
      [nota, target({ action: 'show', when: { field: 'nota', operator: 'lte', value: 2 } })],
      null,
    ],
    [
      'válida (respondida, sem valor)',
      [nome, target({ action: 'hide', when: { field: 'nome', operator: 'answered' } })],
      null,
    ],
    [
      'origem que não existe',
      [nota, target({ action: 'show', when: { field: 'sumiu', operator: 'answered' } })],
      'A condição usa uma pergunta que não existe mais.',
    ],
    [
      'origem arquivo',
      [doc, target({ action: 'show', when: { field: 'doc', operator: 'answered' } })],
      'A condição não pode usar blocos nem campos de arquivo.',
    ],
    [
      'operador que não vale para a origem',
      [cor, target({ action: 'show', when: { field: 'cor', operator: 'gt', value: 'azul' } })],
      'Essa condição não vale para a pergunta escolhida.',
    ],
    [
      'valor ausente',
      [nota, target({ action: 'show', when: { field: 'nota', operator: 'eq' } })],
      'Informe o valor da condição.',
    ],
    [
      'opção que não existe',
      [cor, target({ action: 'show', when: { field: 'cor', operator: 'eq', value: 'roxo' } })],
      'O valor não combina com a pergunta escolhida.',
    ],
    [
      'data fora do formato',
      [
        nascimento,
        target({
          action: 'show',
          when: { field: 'nascimento', operator: 'lt', value: '01/02/2000' },
        }),
      ],
      'O valor não combina com a pergunta escolhida.',
    ],
    [
      'texto longo demais',
      [
        nome,
        target({ action: 'show', when: { field: 'nome', operator: 'eq', value: 'x'.repeat(201) } }),
      ],
      'O valor não combina com a pergunta escolhida.',
    ],
  ])('%s', (_name, items, expected) => {
    expect(errorOf(items)).toBe(expected)
  })

  it('origem movida para depois do campo', () => {
    const items: SchemaItem[] = [
      target({ action: 'show', when: { field: 'nota', operator: 'answered' } }),
      nota,
    ]

    expect(visibilityError(items, 0)).toBe('A pergunta da condição precisa vir antes deste campo.')
  })

  it('entra na validação do item e no limite de 50 por formulário', () => {
    const condition: Visibility = { action: 'show', when: { field: 'nota', operator: 'answered' } }
    const many: SchemaItem[] = [
      nota,
      ...Array.from({ length: 51 }, (_, n) => ({ ...target(condition), id: `campo_${n}` })),
    ]

    expect(validateItems(many).get(GENERAL_ERRORS)).toEqual({
      conditions: 'Use no máximo 50 condições por formulário.',
    })
    expect(
      validateItems([
        target({ action: 'show', when: { field: 'nota', operator: 'answered' } }),
      ]).get('motivo'),
    ).toEqual({ visibility: 'A condição usa uma pergunta que não existe mais.' })
  })
})

describe('describeVisibility', () => {
  it.each<[Visibility, string]>([
    [
      { action: 'show', when: { field: 'nota', operator: 'lte', value: 2 } },
      'Aparece quando "Nota" for menor ou igual a 2',
    ],
    [
      { action: 'hide', when: { field: 'cor', operator: 'eq', value: 'verde' } },
      'Some quando "Cor" for igual a Verde',
    ],
    [
      { action: 'show', when: { field: 'extras', operator: 'includes', value: 'cafe' } },
      'Aparece quando "Extras" incluir Café',
    ],
    [
      { action: 'show', when: { field: 'nascimento', operator: 'gte', value: '2000-01-31' } },
      'Aparece quando "Nascimento" for maior ou igual a 31/01/2000',
    ],
    [
      { action: 'show', when: { field: 'nome', operator: 'not_answered' } },
      'Aparece quando "Nome" não for respondida',
    ],
  ])('%o', (visibility, expected) => {
    expect(describeVisibility(visibility, [nota, cor, extras, nascimento, nome])).toBe(expected)
  })
})
