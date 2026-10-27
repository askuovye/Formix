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

/** Segundos em texto curto: 45s, 2m 14s, 1h 05m. Sem medição = —. */
export function formatDuration(seconds: number | null): string {
  if (seconds === null) return '—'
  if (seconds < 60) return `${seconds}s`

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}m`

  return `${minutes}m ${String(seconds % 60).padStart(2, '0')}s`
}

/**
 * Variação em % contra o período anterior, sem casas. Sem período anterior,
 * ou com ele vazio (nada com o que comparar), `null`.
 */
export function changeOf(
  current: number | null,
  previous: number | null | undefined,
): number | null {
  if (current === null || previous === null || previous === undefined || previous === 0) return null

  return Math.round(((current - previous) / previous) * 100)
}
