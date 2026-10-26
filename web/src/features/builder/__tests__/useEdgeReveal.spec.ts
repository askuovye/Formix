import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'

import { delay } from '@/motion'

import { useEdgeReveal } from '../useEdgeReveal'

function setup() {
  const panel = document.createElement('div')
  const input = document.createElement('input')
  panel.append(input)
  document.body.append(panel)

  const scope = effectScope()
  const reveal = scope.run(() => useEdgeReveal(ref(panel)))
  if (!reveal) throw new Error('escopo')

  return { reveal, panel, input, scope }
}

describe('useEdgeReveal', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal('requestAnimationFrame', (fn: FrameRequestCallback) => setTimeout(fn, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('abre depois de uma pausa na borda; passar rápido não abre', () => {
    const { reveal } = setup()

    reveal.enter()
    reveal.leave()
    vi.advanceTimersByTime(delay.panelOpen)
    expect(reveal.open.value).toBe(false)

    reveal.enter()
    vi.advanceTimersByTime(delay.panelOpen)
    expect(reveal.open.value).toBe(true)
  })

  it('recolhe depois que o mouse sai, mas não se ele voltar a tempo', () => {
    const { reveal } = setup()
    reveal.enter()
    vi.advanceTimersByTime(delay.panelOpen)

    reveal.leave()
    vi.advanceTimersByTime(delay.panelClose / 2)
    reveal.enter()
    vi.advanceTimersByTime(delay.panelClose)
    expect(reveal.open.value).toBe(true)

    reveal.leave()
    vi.advanceTimersByTime(delay.panelClose)
    expect(reveal.open.value).toBe(false)
  })

  it('fica aberto enquanto algo dentro dele tem o foco', () => {
    const { reveal, input } = setup()

    input.focus()
    reveal.focusIn()
    expect(reveal.open.value).toBe(true)

    reveal.leave()
    vi.advanceTimersByTime(delay.panelClose)
    expect(reveal.open.value).toBe(true)

    input.blur()
    reveal.focusOut()
    vi.advanceTimersByTime(delay.panelClose)
    expect(reveal.open.value).toBe(false)
  })

  it('fixado pelo traço da borda, não recolhe ao sair; soltar recolhe', () => {
    const { reveal } = setup()

    reveal.togglePin()
    expect(reveal.open.value).toBe(true)
    reveal.leave()
    vi.advanceTimersByTime(delay.panelClose)
    expect(reveal.open.value).toBe(true)

    reveal.togglePin()
    expect(reveal.open.value).toBe(false)
  })

  it('arrastar um item recolhe o painel para liberar o canvas', () => {
    const { reveal } = setup()
    reveal.enter()
    vi.advanceTimersByTime(delay.panelOpen)

    reveal.dragStart()
    vi.advanceTimersByTime(0)

    expect(reveal.open.value).toBe(false)
  })

  it('clique fora recolhe', async () => {
    const { reveal } = setup()
    reveal.enter()
    vi.advanceTimersByTime(delay.panelOpen)
    await nextTick()

    // Mouse continua "dentro" (sem pointerleave), mas clicou no canvas.
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    vi.advanceTimersByTime(delay.panelClose)

    expect(reveal.open.value).toBe(false)
  })
})
