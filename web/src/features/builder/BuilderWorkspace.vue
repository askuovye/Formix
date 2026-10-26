<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import { builder } from '@/strings'

import BuilderCanvas from './BuilderCanvas.vue'
import BuilderPalette from './BuilderPalette.vue'
import BuilderProperties from './BuilderProperties.vue'
import { useBuilderStore } from './useBuilderStore'

/** Gavetas do celular (abaixo de lg as colunas laterais somem). */
const paletteOpen = defineModel<boolean>('paletteOpen', { default: false })
const propertiesOpen = defineModel<boolean>('propertiesOpen', { default: false })

const store = useBuilderStore()

function isEditable(target: EventTarget | null): boolean {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
  )
}

// Ctrl/⌘+Z desfaz e Ctrl/⌘+Shift+Z ou Ctrl+Y refaz. Dentro de campos de texto
// fica o desfazer nativo do navegador.
function onKeydown(event: KeyboardEvent): void {
  if (!(event.ctrlKey || event.metaKey) || isEditable(event.target)) return

  const key = event.key.toLowerCase()

  if (key === 'z' && !event.shiftKey) {
    event.preventDefault()
    store.undo()
  } else if ((key === 'z' && event.shiftKey) || key === 'y') {
    event.preventDefault()
    store.redo()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="workspace">
    <aside class="workspace__side workspace__side--start">
      <BuilderPalette />
    </aside>

    <main id="conteudo" class="workspace__canvas" tabindex="-1">
      <BuilderCanvas />
    </main>

    <!-- No celular a paleta abre por um botão flutuante, ao alcance do polegar. -->
    <AppButton icon-left="plus" class="workspace__fab" @click="paletteOpen = true">
      {{ builder.toolbar.openPalette }}
    </AppButton>

    <aside class="workspace__side workspace__side--end">
      <BuilderProperties />
    </aside>

    <AppDrawer v-model:open="paletteOpen" :title="builder.palette.title" width="panel">
      <div class="workspace__drawer">
        <BuilderPalette @added="paletteOpen = false" />
      </div>
    </AppDrawer>
    <AppDrawer
      v-model:open="propertiesOpen"
      :title="builder.properties.title"
      side="end"
      width="panel"
    >
      <div class="workspace__drawer">
        <BuilderProperties />
      </div>
    </AppDrawer>

    <p class="workspace__announcer" aria-live="polite">{{ store.announcement }}</p>
  </div>
</template>

<style scoped lang="scss">
.workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-height: 0;

  &__side {
    display: none;
    padding: var(--space-4);
    overflow-y: auto;
    background: var(--color-surface);

    &--start {
      border-inline-end: var(--border-width) solid var(--color-border);
    }

    &--end {
      border-inline-start: var(--border-width) solid var(--color-border);
    }
  }

  &__canvas {
    // O fim da lista fica acima do botão flutuante.
    padding: var(--space-6) var(--space-4) calc(var(--space-16) + var(--space-8));
    overflow-y: auto;
    outline: none;
  }

  &__fab {
    position: fixed;
    inset-block-end: calc(var(--space-4) + env(safe-area-inset-bottom));
    inset-inline-end: var(--space-4);
    z-index: var(--layer-raised);
    box-shadow: var(--shadow-lg);
  }

  &__drawer {
    padding: var(--space-4);
  }

  &__announcer {
    @include visually-hidden;
  }

  @include up(lg) {
    grid-template-columns:
      var(--builder-palette-width) minmax(0, 1fr)
      var(--builder-properties-width);

    &__side {
      display: block;
    }

    &__canvas {
      padding: var(--space-6) var(--space-8);
    }

    &__fab {
      display: none;
    }
  }
}
</style>
