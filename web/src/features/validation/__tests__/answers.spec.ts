import { describe, expect, it } from 'vitest'

import type { SchemaItem } from '@/types/schema'

import { validateAnswers } from '../answers'

const items: SchemaItem[] = [
  { id: 'intro', kind: 'block', type: 'section_title', text: 'Sobre você' },
  {
    id: 'filhos',
    kind: 'input',
    type: 'single_choice',
    label: 'Tem filhos?',
    required: true,
    options: [
      { value: 'sim', label: 'Sim' },
      { value: 'nao', label: 'Não' },
    ],
  },
  {
    id: 'quantos',
    kind: 'input',
    type: 'number',
    label: 'Quantos?',
    required: true,
    visibility: { action: 'show', when: { field: 'filhos', operator: 'eq', value: 'sim' } },
  },
  {
    id: 'nome',
    kind: 'input',
    type: 'short_text',
    label: 'Nome',
    required: true,
    rules: [{ type: 'no_repeated_chars', threshold: 0.8 }],
  },
  {
    id: 'doc',
    kind: 'input',
    type: 'file',
    label: 'Documento',
    required: true,
    file: { accept: ['pdf'], max_files: 1, max_size_mb: 5 },
  },
]

describe('validateAnswers', () => {
  it('campo oculto não é obrigatório e tem o valor descartado', () => {
    const result = validateAnswers(items, { filhos: 'nao', quantos: '3', nome: '  Maria  Souza ' })

    expect(result).toEqual({
      answers: { filhos: 'nao', quantos: null, nome: 'Maria Souza' },
      hidden: ['quantos'],
      errors: {},
    })
  })

  it('campo visível é validado; um erro por campo, com a mensagem da regra', () => {
    const result = validateAnswers(items, { filhos: 'sim', nome: 'aaaaaa' })

    expect(result.hidden).toEqual([])
    expect(result.errors).toEqual({
      quantos: 'Este campo é obrigatório.',
      nome: 'Esta resposta não parece válida. Revise e preencha com uma resposta de verdade.',
    })
  })

  it('arquivos ficam de fora (fluxo próprio)', () => {
    const result = validateAnswers(items, { filhos: 'nao', nome: 'Maria' })

    expect(result.answers).not.toHaveProperty('doc')
    expect(result.errors).not.toHaveProperty('doc')
  })
})
