import { delay, duration } from '@/motion'

/** Grupo do SortableJS compartilhado entre a paleta (origem) e o canvas (destino). */
export const BUILDER_DRAG_GROUP = 'builder'

export function prefersReducedMotion(): boolean {
  return (
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  )
}

/**
 * Duração do deslizar dos vizinhos no SortableJS (ms), do token de movimento.
 * Com movimento reduzido, sem animação (AGENTS.md §7).
 */
export function sortableAnimationMs(): number {
  return prefersReducedMotion() ? 0 : duration.base * 1000
}

/**
 * Opções comuns do SortableJS na paleta e no canvas. O arrasto é sempre
 * "fallback" (sem o fantasma nativo do navegador): a cópia do Sortable fica
 * escondida e o card levantado é o do BuilderDragLift. No toque, segurar um
 * instante antes de arrastar, para o deslizar do dedo continuar rolando a tela.
 */
export const SORTABLE_OPTIONS = {
  forceFallback: true,
  fallbackTolerance: 4,
  delay: delay.dragTouch,
  delayOnTouchOnly: true,
  touchStartThreshold: 4,
} as const
