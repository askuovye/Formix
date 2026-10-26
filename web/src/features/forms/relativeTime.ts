const units: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 86_400],
  ['month', 30 * 86_400],
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
]

const format = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' })

/** "agora", "há 5 minutos", "ontem", "há 3 meses"… a partir de uma data ISO 8601. */
export function relativeTime(iso: string, now: number = Date.now()): string {
  const seconds = Math.round((new Date(iso).getTime() - now) / 1000)

  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) {
      return format.format(Math.round(seconds / size), unit)
    }
  }

  return format.format(0, 'second')
}
