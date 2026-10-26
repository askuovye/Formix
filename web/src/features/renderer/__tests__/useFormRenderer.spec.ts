import { describe, expect, it } from 'vitest'

import type { SchemaItem } from '@/types/schema'

import { splitSteps, useFormRenderer } from '../useFormRenderer'

const items: SchemaItem[] = [
  { id: 'intro', kind: 'block', type: 'section_title', text: 'Sobre você' },
  { id: 'nome', kind: 'input', type: 'short_text', label: 'Nome', required: true },
  {
    id: 'filhos',
    kind: 'input',
    type: 'single_choice',
    label: 'Tem filhos?',
    options: [
      { value: 'sim', label: 'Sim' },
      { value: 'nao', label: 'Não' },
    ],
  },
  { id: 'quebra', kind: 'block', type: 'page_break' },
  {
    id: 'quantos',
    kind: 'input',
    type: 'number',
    label: 'Quantos?',
    required: true,
    visibility: { action: 'show', when: { field: 'filhos', operator: 'eq', value: 'sim' } },
  },
  { id: 'quebra2', kind: 'block', type: 'page_break' },
  { id: 'nota', kind: 'input', type: 'rating', label: 'Nota', rating: { max: 5 } },
]

describe('splitSteps', () => {
  it('quebra nas quebras de página e descarta etapas vazias', () => {
    const steps = splitSteps([
      { id: 'q0', kind: 'block', type: 'page_break' },
      ...items,
      { id: 'q9', kind: 'block', type: 'page_break' },
    ])

    expect(steps.map((step) => step.map((item) => item.id))).toEqual([
      ['intro', 'nome', 'filhos'],
      ['quantos'],
      ['nota'],
    ])
  })
})

describe('useFormRenderer', () => {
  it('não avança com erro na etapa; os erros só aparecem depois da tentativa', () => {
    const state = useFormRenderer(items)

    expect(state.errors.value).toEqual({})
    expect(state.next()).toBe(false)
    expect(state.errors.value).toEqual({ nome: 'Este campo é obrigatório.' })
    expect(state.stepIndex.value).toBe(0)

    state.update('nome', 'Maria')
    expect(state.errors.value).toEqual({})
  })

  it('pula a etapa em que tudo está oculto, nos dois sentidos', () => {
    const state = useFormRenderer(items)
    state.update('nome', 'Maria')
    state.update('filhos', 'nao')

    expect(state.next()).toBe(true)
    expect(state.stepIndex.value).toBe(2)
    expect(state.isLastStep.value).toBe(true)

    state.back()
    expect(state.stepIndex.value).toBe(0)
  })

  it('campo que aparece pela condição passa a ser exigido', () => {
    const state = useFormRenderer(items)
    state.update('nome', 'Maria')
    state.update('filhos', 'sim')

    expect(state.hidden.value.has('quantos')).toBe(false)
    expect(state.next()).toBe(true)
    expect(state.stepIndex.value).toBe(1)
    expect(state.next()).toBe(false)
    expect(state.errors.value).toEqual({ quantos: 'Este campo é obrigatório.' })
  })

  it('payload: normaliza, manda ocultos como null e, com erro, volta à etapa dele', () => {
    const state = useFormRenderer(items)
    state.update('filhos', 'nao')
    state.stepIndex.value = 2

    expect(state.payload()).toBeNull()
    expect(state.stepIndex.value).toBe(0)

    state.update('nome', '  Maria   Souza ')
    expect(state.payload()).toEqual({
      nome: 'Maria Souza',
      filhos: 'nao',
      quantos: null,
      nota: null,
    })
  })

  it('erros do servidor: vão para o campo e somem quando ele muda; o resto volta', () => {
    const state = useFormRenderer(items)
    state.update('filhos', 'nao')
    state.stepIndex.value = 2

    const unmatched = state.setServerErrors({
      nome: 'O campo Nome é inválido.',
      quantos: 'Oculto, não aparece.',
      captcha: 'Confirme que você não é um robô.',
    })

    expect(state.errors.value).toEqual({ nome: 'O campo Nome é inválido.' })
    expect(state.stepIndex.value).toBe(0)
    expect(unmatched).toEqual(['Oculto, não aparece.', 'Confirme que você não é um robô.'])

    state.update('nome', 'Maria')
    expect(state.errors.value).toEqual({})
  })
})
