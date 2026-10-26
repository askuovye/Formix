import { duration } from '@/motion'

/** Grupo do SortableJS compartilhado entre a paleta (origem) e o canvas (destino). */
export const BUILDER_DRAG_GROUP = 'builder'

/**
 * Duração do deslizar dos vizinhos no SortableJS (ms), do token de movimento.
 * Com movimento reduzido, sem animação (AGENTS.md §7).
 */
export function sortableAnimationMs(): number {
  const reduced =
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  return reduced ? 0 : duration.base * 1000
}
