import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'

import * as resultsApi from '@/api/results'
import { RESULTS_POLL_MS } from '@/api/results'
import type { FormResults, ResultField } from '@/types/api'

import { formatDate, formatDecimal, formatShortDate, percentOf } from '../format'
import ResultFieldCard from '../ResultFieldCard.vue'
import ResultsPanel from '../ResultsPanel.vue'
import ResultsTimeline from '../ResultsTimeline.vue'

// Só a busca é falsa; o intervalo das rebuscas é o de verdade.
vi.mock('@/api/results', async (original) => ({
  ...(await original<typeof resultsApi>()),
  fetchResults: vi.fn(),
}))

describe('formatação dos resultados', () => {
  it('formata média, porcentagem e datas em pt-BR', () => {
    expect(formatDecimal(4.25)).toBe('4,3')
    expect(formatDecimal(3)).toBe('3')
    expect(formatDecimal(null)).toBe('—')
    expect(percentOf(1, 3)).toBe(33)
    expect(percentOf(2, 0)).toBe(0)
    expect(formatDate('2026-10-05')).toBe('05/10/2026')
    expect(formatDate(null)).toBe('—')
    expect(formatShortDate('2026-10-05')).toBe('05/10')
  })
})

function card(field: ResultField, total = 4) {
  return mount(ResultFieldCard, { props: { field, total } })
}

describe('ResultFieldCard', () => {
  it('escolha: contagem e % de quem respondeu, inclusive opções sem voto', () => {
    const wrapper = card({
      id: 'curso',
      label: 'Curso',
      type: 'multiple_choice',
      answered: 3,
      options: [
        { value: 'a', label: 'Administração', count: 3 },
        { value: 'd', label: 'Direito', count: 1 },
        { value: 'm', label: 'Medicina', count: 0 },
      ],
    })

    expect(wrapper.text()).toContain('3 de 4 responderam')
    const rows = wrapper.findAll('.choice-bars__row')
    expect(rows.map((row) => row.attributes('aria-label'))).toEqual([
      'Administração: 3 respostas (100%)',
      'Direito: 1 resposta (33%)',
      'Medicina: 0 respostas (0%)',
    ])
    // Sem voto, sem barra (fica só o trilho).
    expect(rows[2]?.find('.choice-bars__fill').exists()).toBe(false)
  })

  it('estrelas: média com uma casa e todas as colunas da escala', () => {
    const wrapper = card({
      id: 'nota',
      label: 'Nota',
      type: 'rating',
      answered: 3,
      average: 4.333,
      distribution: [1, 2, 3, 4, 5].map((value) => ({
        value,
        count: value === 4 ? 2 : value === 5 ? 1 : 0,
      })),
    })

    expect(wrapper.get('.result-field__number').text()).toBe('4,3')
    expect(wrapper.get('.result-field__number').attributes('data-count')).toBe('4.333')
    expect(wrapper.findAll('.distribution__column')).toHaveLength(5)
    expect(wrapper.findAll('.distribution__bar--empty')).toHaveLength(3)
  })

  it('número e data: menor, média, maior e intervalo de datas', () => {
    const number = card({
      id: 'idade',
      label: 'Idade',
      type: 'number',
      answered: 2,
      min: 18,
      max: 40.5,
      average: 29.25,
    })
    expect(number.findAll('.result-field__stat-value').map((el) => el.text())).toEqual([
      '18',
      '29,3',
      '40,5',
    ])

    const date = card({
      id: 'nasc',
      label: 'Nascimento',
      type: 'date',
      answered: 2,
      earliest: '1990-01-31',
      latest: '2001-12-01',
    })
    expect(date.text()).toContain('31/01/1990')
    expect(date.text()).toContain('01/12/2001')
  })

  it('texto: últimas respostas clicáveis abrem a resposta', async () => {
    const wrapper = card({
      id: 'nome',
      label: 'Nome',
      type: 'short_text',
      answered: 2,
      latest: [
        { submission_id: 's2', value: 'João Lima', created_at: '2026-10-27T10:00:00Z' },
        { submission_id: 's1', value: 'Maria Lima', created_at: '2026-10-26T10:00:00Z' },
      ],
    })

    const buttons = wrapper.findAll('.result-field__text')
    expect(buttons.map((button) => button.text())).toEqual(['João Lima', 'Maria Lima'])

    await buttons[1]?.trigger('click')
    expect(wrapper.emitted('open')).toEqual([['s1']])
  })

  it('sem respostas no campo, avisa em vez de desenhar', () => {
    const wrapper = card({ id: 'obs', label: 'Obs.', type: 'long_text', answered: 0, latest: [] })

    expect(wrapper.text()).toContain('Ninguém respondeu este campo.')
    expect(wrapper.find('.result-field__latest').exists()).toBe(false)
  })

  it('arquivo: conta respostas com anexo, sem a linha de "responderam"', () => {
    const wrapper = card({ id: 'doc', label: 'Documento', type: 'file', answered: 1 })

    expect(wrapper.text()).toContain('1 resposta com arquivo')
    expect(wrapper.find('.result-field__answered').exists()).toBe(false)
  })
})

describe('ResultsTimeline', () => {
  const timeline = (unit: 'day' | 'week'): FormResults['timeline'] => ({
    unit,
    points: [
      { date: '2026-10-01', count: 2 },
      { date: '2026-10-02', count: 0 },
      { date: '2026-10-03', count: 4 },
      { date: '2026-10-04', count: 1 },
      { date: '2026-10-05', count: 0 },
    ],
  })

  it('altura relativa ao pico, rótulos só no início, meio e fim e tabela acessível', () => {
    const wrapper = mount(ResultsTimeline, { props: { timeline: timeline('day') } })

    const bars = wrapper.findAll('.timeline__bar')
    expect(bars.map((bar) => (bar.element as HTMLElement).style.blockSize)).toEqual([
      '50%',
      '0%',
      '100%',
      '25%',
      '0%',
    ])
    expect(wrapper.findAll('.timeline__tick').map((tick) => tick.text())).toEqual([
      '01/10',
      '',
      '03/10',
      '',
      '05/10',
    ])
    expect(wrapper.text()).toContain('Respostas por dia')
    expect(wrapper.text()).toContain('máx. 4')
    expect(wrapper.findAll('.timeline__table tr')).toHaveLength(5)
  })

  it('por semana, o tooltip diz de que semana é', () => {
    const wrapper = mount(ResultsTimeline, { props: { timeline: timeline('week') } })

    expect(wrapper.text()).toContain('Respostas por semana')
    expect(wrapper.get('.timeline__slot').attributes('title')).toBe('semana de 01/10: 2 respostas')
  })
})

describe('ResultsPanel', () => {
  const data = (total: number): FormResults => ({
    total,
    last_7_days: total,
    edited: 0,
    timeline: { unit: 'day', points: [{ date: '2026-10-27', count: total }] },
    fields: [],
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('rebusca sozinho e troca os números sem montar de novo', async () => {
    vi.useFakeTimers()
    // Sem animação: o teste olha só os valores.
    vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: true,
    } as MediaQueryList)
    vi.mocked(resultsApi.fetchResults).mockResolvedValueOnce(data(2)).mockResolvedValueOnce(data(5))
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = mount(ResultsPanel, {
      props: { formId: 'f1', filters: {} },
      global: { plugins: [[VueQueryPlugin, { queryClient }]] },
    })
    await flushPromises()

    const content = wrapper.get('.results').element
    expect(wrapper.get('.results__stat-value').text()).toBe('2')
    expect(wrapper.text()).toContain('Atualiza sozinho')

    await vi.advanceTimersByTimeAsync(RESULTS_POLL_MS)
    await flushPromises()

    expect(resultsApi.fetchResults).toHaveBeenCalledTimes(2)
    expect(wrapper.get('.results__stat-value').text()).toBe('5')
    expect(wrapper.get('.results').element).toBe(content)
    wrapper.unmount()
  })
})
