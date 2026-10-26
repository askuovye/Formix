<script setup lang="ts">
import BuilderPalette from './BuilderPalette.vue'
import BuilderRail from './BuilderRail.vue'
import BuilderSettings from './BuilderSettings.vue'
import type { BuilderSection } from './sections'

/**
 * Coluna da esquerda: seções (rail) + painel da seção escolhida. No desktop o
 * rail fica ao lado do painel; na gaveta do celular, em cima.
 */
const props = withDefaults(
  defineProps<{
    layout?: 'column' | 'drawer'
  }>(),
  { layout: 'column' },
)

const emit = defineEmits<{
  /** Um item foi adicionado pelo clique (a gaveta do celular fecha). */
  added: []
}>()

const section = defineModel<BuilderSection>('section', { required: true })
</script>

<template>
  <div class="sidebar" :class="`sidebar--${props.layout}`">
    <BuilderRail
      v-model="section"
      :orientation="props.layout === 'drawer' ? 'horizontal' : 'vertical'"
      class="sidebar__rail"
    />
    <div class="sidebar__panel">
      <BuilderPalette
        v-if="section === 'fields' || section === 'elements'"
        :section="section"
        @added="emit('added')"
      />
      <BuilderSettings v-else-if="section === 'settings'" />
    </div>
  </div>
</template>

<style scoped lang="scss">
.sidebar {
  display: grid;
  min-height: 0;

  &--column {
    grid-template-columns: var(--builder-rail-width) minmax(0, 1fr);
    height: 100%;
  }

  &--drawer {
    grid-template-rows: auto minmax(0, 1fr);
  }

  &__rail {
    padding: var(--space-2) var(--space-1);
  }

  &--column &__rail {
    border-inline-end: var(--border-width) solid var(--color-border);
  }

  &--drawer &__rail {
    border-block-end: var(--border-width) solid var(--color-border);
  }

  &__panel {
    min-height: 0;
    padding: var(--space-4);
    overflow-y: auto;
  }
}
</style>
