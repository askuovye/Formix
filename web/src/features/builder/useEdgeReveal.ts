import { onScopeDispose, ref, watch, type Ref } from 'vue'

import { delay } from '@/motion'

/** Conteúdo de popover/listbox do Reka (vai para um portal, fora do painel). */
const POPPER_SELECTOR = '[data-reka-popper-content-wrapper]'

/**
 * Painel lateral que abre ao encostar o mouse na borda da tela e recolhe
 * quando o mouse sai. Fica aberto enquanto:
 * - o mouse está sobre ele;
 * - algo dentro dele tem o foco (digitando, por exemplo);
 * - um popover aberto a partir dele (select, menu) está na tela;
 * - foi fixado pelo botão da borda.
 */
export function useEdgeReveal(panel: Ref<HTMLElement | null>) {
  const open = ref(false)
  const pinned = ref(false)

  let hovered = false
  let timer: ReturnType<typeof setTimeout> | undefined

  function busy(): boolean {
    const active = document.activeElement
    return (
      hovered ||
      pinned.value ||
      (active !== null && panel.value?.contains(active) === true) ||
      document.querySelector(POPPER_SELECTOR) !== null
    )
  }

  function schedule(fn: () => void, ms: number): void {
    clearTimeout(timer)
    timer = setTimeout(fn, ms)
  }

  /** Mouse chegou na borda ou no painel. */
  function enter(): void {
    hovered = true
    if (!open.value) schedule(() => hovered && (open.value = true), delay.panelOpen)
    else clearTimeout(timer)
  }

  /** Mouse saiu: recolhe depois de uma pausa, se nada mais o segurar. */
  function leave(): void {
    hovered = false
    schedule(closeIfIdle, delay.panelClose)
  }

  function closeIfIdle(): void {
    if (!busy()) open.value = false
  }

  /**
   * Abre sem o mouse (ex.: selecionou um campo no canvas). Fica até um clique
   * fora; um clique que seleciona outro campo chama `show` de novo e cancela
   * o recolher.
   */
  function show(): void {
    clearTimeout(timer)
    open.value = true
  }

  /** Foco entrou (teclado): abre na hora. */
  function focusIn(): void {
    clearTimeout(timer)
    open.value = true
  }

  /** Foco saiu: espera o novo foco assentar (pode ter ido para um popover). */
  function focusOut(): void {
    schedule(closeIfIdle, delay.panelClose)
  }

  function togglePin(): void {
    pinned.value = !pinned.value
    open.value = pinned.value
    if (!pinned.value) hovered = false
  }

  /** Começou a arrastar um item do painel: recolhe para liberar o canvas. */
  function dragStart(): void {
    hovered = false
    // No quadro seguinte: o navegador já capturou a imagem do arraste.
    requestAnimationFrame(() => {
      if (!pinned.value) open.value = false
    })
  }

  // Clique fora (no canvas, por exemplo) recolhe, mesmo que o foco estivesse
  // num popover aberto a partir do painel.
  function pointerDownOutside(event: PointerEvent): void {
    const target = event.target
    if (!(target instanceof Node) || panel.value?.contains(target)) return
    if (target instanceof Element && target.closest(POPPER_SELECTOR)) return

    hovered = false
    schedule(closeIfIdle, delay.panelClose)
  }

  watch(open, (isOpen) => {
    if (isOpen) document.addEventListener('pointerdown', pointerDownOutside)
    else document.removeEventListener('pointerdown', pointerDownOutside)
  })

  onScopeDispose(() => {
    clearTimeout(timer)
    document.removeEventListener('pointerdown', pointerDownOutside)
  })

  return { open, pinned, enter, leave, show, focusIn, focusOut, togglePin, dragStart }
}
