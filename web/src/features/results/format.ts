/** Formatação dos números e datas dos resultados (pt-BR). */

export function formatCount(value: number): string {
  return value.toLocaleString('pt-BR')
}

/** Média e números com no máximo uma casa: 4,2. */
export function formatDecimal(value: number | null): string {
  return value === null ? '—' : value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })
}

/** Parte de um total em %, sem casas: 38%. Total zero = 0%. */
export function percentOf(value: number, total: number): number {
  return total > 0 ? Math.round((value / total) * 100) : 0
}

/** `AAAA-MM-DD` → `dd/mm/aaaa` (sem fuso: é uma data do calendário). */
export function formatDate(iso: string | null): string {
  if (!iso) return '—'
  const [year, month, day] = iso.split('-')

  return `${day}/${month}/${year}`
}

/** `AAAA-MM-DD` → `dd/mm`, para o eixo da linha do tempo. */
export function formatShortDate(iso: string): string {
  const [, month, day] = iso.split('-')

  return `${day}/${month}`
}
