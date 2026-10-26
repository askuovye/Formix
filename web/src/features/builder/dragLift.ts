import { shallowRef } from 'vue'

/** Parte do evento do SortableJS usada aqui (vuedraggable repassa o original). */
export interface SortableDragEvent {
  item: HTMLElement
  originalEvent?: Event
}

/** Um arrasto em andamento: a cópia levantada e por onde ela anda. */
export interface LiftSession {
  /** Cópia do item arrastado, mostrada pelo BuilderDragLift. */
  node: HTMLElement
  /** Item real: durante o arrasto é a vaga onde o card vai cair. */
  source: HTMLElement
  /** Item do canvas: a cópia leva o tema do formulário. */
  themed: boolean
  rect: DOMRect
  /** Distância do ponteiro ao canto do card no início (o card não "pula"). */
  offset: { x: number; y: number }
  pointer: { x: number; y: number }
  dropped: boolean
}

export const liftSession = shallowRef<LiftSession | null>(null)

export function pointerOf(event: Event | undefined): { x: number; y: number } | null {
  if (!event) return null
  if ('touches' in event) {
    const touch = (event as TouchEvent).touches[0] ?? (event as TouchEvent).changedTouches[0]
    return touch ? { x: touch.clientX, y: touch.clientY } : null
  }
  if ('clientX' in event) {
    const { clientX, clientY } = event as MouseEvent
    return { x: clientX, y: clientY }
  }

  return null
}

/**
 * Handlers `@start`/`@end` do vuedraggable que levantam o card.
 * `ghostClass` é tirada da cópia (no item real ela marca a vaga).
 */
export function useDragLift(options: { themed: boolean; ghostClass: string }) {
  function start(event: SortableDragEvent): void {
    const rect = event.item.getBoundingClientRect()
    const pointer = pointerOf(event.originalEvent) ?? {
      x: rect.left + rect.width / 2,
      y: rect.top + rect.height / 2,
    }
    const node = event.item.cloneNode(true) as HTMLElement
    node.classList.remove(options.ghostClass)
    node.removeAttribute('id')

    liftSession.value = {
      node,
      source: event.item,
      themed: options.themed,
      rect,
      offset: { x: pointer.x - rect.left, y: pointer.y - rect.top },
      pointer,
      dropped: false,
    }
  }

  function end(): void {
    const session = liftSession.value
    if (session) liftSession.value = { ...session, dropped: true }
  }

  return { start, end }
}
