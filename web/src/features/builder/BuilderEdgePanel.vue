<script setup lang="ts">
import { motion } from 'motion-v'
import { computed, ref, useId } from 'vue'

import { distance, spring } from '@/motion'
import { builder } from '@/strings'

import { useEdgeReveal } from './useEdgeReveal'

/**
 * Painel lateral do desktop que fica recolhido e desliza por cima do canvas
 * ao encostar o mouse na borda da tela. O traço na borda abre e fixa pelo
 * clique (toque e teclado).
 */
const props = defineProps<{
  side: 'start' | 'end'
}>()

defineSlots<{ default: () => unknown }>()

const root = ref<HTMLElement | null>(null)
const reveal = useEdgeReveal(root)
const { open, pinned } = reveal
const panelId = useId()
const t = computed(() => builder.edge[props.side])

// Recolhido: fora da tela pela própria borda. O conteúdo entra um pouco
// depois e com um leve deslocamento, dando profundidade ao movimento.
defineExpose({ show: reveal.show })

const hiddenX = props.side === 'start' ? '-100%' : '100%'
const contentOffset = props.side === 'start' ? -distance.lg : distance.lg
</script>

<template>
  <div
    ref="root"
    class="edge-panel"
    :class="[`edge-panel--${props.side}`, { 'edge-panel--open': open }]"
    @focusin="reveal.focusIn"
    @focusout="reveal.focusOut"
    @dragstart="reveal.dragStart"
  >
    <div class="edge-panel__zone" @pointerenter="reveal.enter" @pointerleave="reveal.leave" />

    <motion.div
      class="edge-panel__panel"
      :initial="false"
      :animate="{ x: open ? 0 : hiddenX }"
      :transition="spring.panel"
    >
      <section
        :id="panelId"
        class="edge-panel__surface"
        :aria-label="t.region"
        @pointerenter="reveal.enter"
        @pointerleave="reveal.leave"
      >
        <motion.div
          class="edge-panel__content"
          :initial="false"
          :animate="{ opacity: open ? 1 : 0, x: open ? 0 : contentOffset }"
          :transition="spring.panel"
        >
          <slot />
        </motion.div>
      </section>

      <button
        type="button"
        class="edge-panel__handle"
        :class="{ 'edge-panel__handle--pinned': pinned }"
        :aria-expanded="open"
        :aria-controls="panelId"
        :aria-label="pinned ? t.unpin : t.pin"
        @pointerenter="reveal.enter"
        @pointerleave="reveal.leave"
        @click="reveal.togglePin"
      />
    </motion.div>
  </div>
</template>

<style scoped lang="scss">
.edge-panel {
  position: absolute;
  inset-block: 0;
  z-index: var(--layer-raised);
  width: var(--edge-panel-width);
  // A área do painel não bloqueia o canvas; só a borda, o traço e o painel
  // aberto recebem o mouse.
  pointer-events: none;

  &--start {
    --edge-panel-width: calc(var(--builder-rail-width) + var(--builder-palette-width));

    inset-inline-start: 0;
  }

  &--end {
    --edge-panel-width: var(--builder-properties-width);

    inset-inline-end: 0;
  }

  &__zone {
    position: absolute;
    inset-block: 0;
    width: var(--builder-edge-zone);
    pointer-events: auto;
  }

  &--start &__zone {
    inset-inline-start: 0;
  }

  &--end &__zone {
    inset-inline-end: 0;
  }

  &__panel {
    position: absolute;
    inset: 0;
    pointer-events: auto;
  }

  &__surface {
    height: 100%;
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--color-surface);
    transition: box-shadow var(--duration-slow) var(--ease-out);
  }

  &--start &__surface {
    border-inline-end: var(--border-width) solid var(--color-border);
  }

  &--end &__surface {
    border-inline-start: var(--border-width) solid var(--color-border);
  }

  &--open &__surface {
    box-shadow: var(--shadow-lg);
  }

  &__content {
    height: 100%;
  }

  // Traço discreto na borda da tela (com o painel recolhido) ou na beirada do
  // painel (aberto). A área de clique é maior que o traço.
  &__handle {
    position: absolute;
    inset-block-start: 50%;
    width: var(--builder-edge-zone);
    height: var(--space-16);
    padding: 0;
    background: none;
    border: 0;
    cursor: pointer;
    transform: translateY(-50%);

    &::before {
      position: absolute;
      inset-block: var(--space-2);
      inset-inline-start: 50%;
      width: var(--builder-edge-handle);
      content: '';
      background: var(--color-border-strong);
      border-radius: var(--radius-full);
      transform: translateX(-50%);
      transition: background-color var(--duration-base) var(--ease-out);
    }

    &:hover::before {
      background: var(--color-text-muted);
    }

    &--pinned::before {
      background: var(--color-primary);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &--start &__handle {
    inset-inline-start: 100%;
  }

  &--end &__handle {
    inset-inline-end: 100%;
  }
}
</style>
