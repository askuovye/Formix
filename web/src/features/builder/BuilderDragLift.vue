<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'

import FormThemeScope from '@/features/renderer/FormThemeScope.vue'
import { drag, duration } from '@/motion'

import { prefersReducedMotion } from './dragAndDrop'
import { liftSession, pointerOf } from './dragLift'
import { useBuilderStore } from './useBuilderStore'

/**
 * Card levantado durante o arrasto (paleta e canvas): segue o ponteiro com um
 * leve atraso, inclina conforme a velocidade e a sombra corre ao contrário
 * (paralaxe). Ao soltar, assenta na vaga e some. Um único requestAnimationFrame
 * enquanto arrasta, só com transform/opacity; com movimento reduzido, segue
 * o ponteiro sem atraso nem inclinação.
 */
const store = useBuilderStore()
const card = useTemplateRef<HTMLElement>('card')
const shadow = useTemplateRef<HTMLElement>('shadow')
const leaving = ref(false)

let frame = 0
let last = 0
let reduced = false
const state = { x: 0, y: 0, vx: 0, vy: 0, tiltX: 0, tiltY: 0, scale: 1 }
let target = { left: 0, top: 0 }

function clamp(value: number, limit: number): number {
  return Math.max(-limit, Math.min(limit, value))
}

function onPointer(event: Event): void {
  const session = liftSession.value
  const pointer = pointerOf(event)
  if (session && pointer && !session.dropped) session.pointer = pointer
}

function tick(now: number): void {
  const session = liftSession.value
  if (!session || !card.value || !shadow.value) return

  const dt = Math.min(now - last, 50) / 1000 || 1 / 60
  last = now

  // Leitura antes das escritas: a vaga real, enquanto ainda está no lugar certo.
  if (!session.dropped) {
    const spot = session.source.getBoundingClientRect()
    target = { left: spot.left, top: spot.top }
  }
  const goal = session.dropped
    ? target
    : { left: session.pointer.x - session.offset.x, top: session.pointer.y - session.offset.y }

  const follow = reduced ? 1 : 1 - Math.exp(-dt / drag.follow)
  const x = state.x + (goal.left - state.x) * follow
  const y = state.y + (goal.top - state.y) * follow
  state.vx = (x - state.x) / dt
  state.vy = (y - state.y) / dt
  state.x = x
  state.y = y

  const tiltY = reduced ? 0 : clamp(state.vx * drag.tilt, drag.maxTilt)
  const tiltX = reduced ? 0 : clamp(-state.vy * drag.tilt, drag.maxTilt)
  state.tiltY += (tiltY - state.tiltY) * follow
  state.tiltX += (tiltX - state.tiltX) * follow
  const scale = session.dropped || reduced ? 1 : drag.liftScale
  state.scale += (scale - state.scale) * follow

  card.value.style.transform =
    `translate3d(${state.x}px, ${state.y}px, 0) perspective(${drag.perspective}px) ` +
    `rotateX(${state.tiltX}deg) rotateY(${state.tiltY}deg) rotate(${state.tiltY / 3}deg) ` +
    `scale(${state.scale})`
  const lift = (state.scale - 1) / (drag.liftScale - 1 || 1)
  shadow.value.style.transform =
    `translate3d(${state.x - clamp(state.vx * drag.shadow, drag.maxShadow)}px, ` +
    `${state.y - clamp(state.vy * drag.shadow, drag.maxShadow)}px, 0) scale(${state.scale})`
  shadow.value.style.opacity = String(Math.max(0, Math.min(1, lift)))

  const settled =
    Math.abs(goal.left - state.x) < 0.5 &&
    Math.abs(goal.top - state.y) < 0.5 &&
    Math.abs(state.scale - 1) < 0.002
  if (session.dropped && settled) return finish()

  frame = requestAnimationFrame(tick)
}

function finish(): void {
  leaving.value = true
  window.setTimeout(() => {
    liftSession.value = null
  }, duration.fast * 1000)
}

function stop(): void {
  cancelAnimationFrame(frame)
  window.removeEventListener('pointermove', onPointer)
  window.removeEventListener('touchmove', onPointer)
}

watch(
  () => liftSession.value?.node,
  async (node, previous) => {
    if (node === previous) return
    stop()
    leaving.value = false
    if (!node) return

    await nextTick()
    const session = liftSession.value
    if (!session || !card.value) return

    reduced = prefersReducedMotion()
    Object.assign(state, {
      x: session.rect.left,
      y: session.rect.top,
      vx: 0,
      vy: 0,
      tiltX: 0,
      tiltY: 0,
      scale: 1,
    })
    target = { left: session.rect.left, top: session.rect.top }
    card.value.replaceChildren(node)
    window.addEventListener('pointermove', onPointer, { passive: true })
    window.addEventListener('touchmove', onPointer, { passive: true })
    last = performance.now()
    frame = requestAnimationFrame(tick)
  },
)

onBeforeUnmount(() => {
  stop()
  liftSession.value = null
})
</script>

<template>
  <Teleport to="body">
    <component
      :is="liftSession.themed ? FormThemeScope : 'div'"
      v-if="liftSession"
      v-bind="liftSession.themed ? { theme: store.schema.theme } : {}"
      class="drag-lift"
      :class="{ 'drag-lift--leaving': leaving }"
      aria-hidden="true"
    >
      <div
        ref="shadow"
        class="drag-lift__shadow"
        :style="{ width: `${liftSession.rect.width}px`, height: `${liftSession.rect.height}px` }"
      />
      <div
        ref="card"
        class="drag-lift__card"
        :style="{ width: `${liftSession.rect.width}px`, height: `${liftSession.rect.height}px` }"
      />
    </component>
  </Teleport>
</template>

<style scoped lang="scss">
// Host sem tamanho preso ao canto da tela: o fundo do tema não aparece e as
// coordenadas dos filhos são as da viewport.
.drag-lift {
  position: fixed;
  inset: 0 auto auto 0;
  z-index: var(--layer-tooltip);
  inline-size: 0;
  block-size: 0;
  pointer-events: none;
  transition: opacity var(--duration-fast) var(--ease-out);

  &--leaving {
    opacity: 0;
  }

  &__shadow,
  &__card {
    position: absolute;
    inset: 0 auto auto 0;
    transform-origin: center;
    will-change: transform;
  }

  &__shadow {
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    opacity: 0;
  }

  &__card {
    list-style: none;
    cursor: grabbing;
  }
}
</style>
