import type { SubmissionFilters } from '@/api/submissions'

/** Períodos do filtro, em dias (contando hoje); `all` = sem limite. */
export const PERIODS = ['all', '7', '30', '90'] as const
export type Period = (typeof PERIODS)[number]

export const SORTS = ['newest', 'oldest'] as const
export type Sort = (typeof SORTS)[number]

const brasiliaDay = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' })

/**
 * Primeiro dia do período em `AAAA-MM-DD` no fuso de Brasília (o servidor lê
 * a data como o dia inteiro em Brasília). "Últimos 7 dias" inclui hoje.
 */
export function periodStart(period: Period, now: number = Date.now()): string | undefined {
  if (period === 'all') return undefined
  const days = Number(period) - 1

  return brasiliaDay.format(new Date(now - days * 86_400_000))
}

export function isPeriod(value: unknown): value is Period {
  return PERIODS.some((period) => period === value)
}

export function isSort(value: unknown): value is Sort {
  return SORTS.some((sort) => sort === value)
}

/** Filtros da API a partir das escolhas da tela. */
export function toFilters(
  form: string | undefined,
  period: Period,
  sort: Sort,
  search = '',
): SubmissionFilters {
  const from = periodStart(period)

  return {
    sort,
    ...(form ? { form } : {}),
    ...(from ? { from } : {}),
    ...(search ? { q: search } : {}),
  }
}
