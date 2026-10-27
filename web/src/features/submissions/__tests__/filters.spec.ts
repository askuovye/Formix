import { describe, expect, it } from 'vitest'

import { periodStart, toFilters } from '../filters'

describe('filtros de respostas', () => {
  // 26/10/2026 01:30 em UTC = 25/10 22:30 em Brasília.
  const lateNightUtc = Date.UTC(2026, 9, 26, 1, 30)

  it('início do período no dia de Brasília, contando hoje', () => {
    expect(periodStart('all', lateNightUtc)).toBeUndefined()
    expect(periodStart('7', lateNightUtc)).toBe('2026-10-19')
    expect(periodStart('30', lateNightUtc)).toBe('2026-09-26')
  })

  it('só manda o que foi escolhido', () => {
    expect(toFilters(undefined, 'all', 'newest')).toEqual({ sort: 'newest' })
    expect(toFilters('f1', 'all', 'oldest')).toEqual({ sort: 'oldest', form: 'f1' })
    expect(toFilters(undefined, 'all', 'newest', 'joão')).toEqual({ sort: 'newest', q: 'joão' })
  })
})
