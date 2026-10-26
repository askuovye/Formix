<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import { builder } from '@/strings'
import type { SchemaItem } from '@/types/schema'

import BuilderItemPreview from './BuilderItemPreview.vue'
import { paletteEntryFor } from './catalog'

const props = defineProps<{
  item: SchemaItem
  selected: boolean
  isFirst: boolean
  isLast: boolean
}>()

const emit = defineEmits<{
  select: []
  moveUp: []
  moveDown: []
  duplicate: []
  remove: []
}>()

const t = builder.canvas

const entry = computed(() => paletteEntryFor(props.item))
const typeName = computed(() => builder.types[entry.value.key])

/** Nome do item para leitores de tela e avisos: o rótulo, o texto ou o tipo. */
const name = computed(() => {
  const { item } = props
  if (item.kind === 'input') return item.label
  if (item.type === 'section_title' || item.type === 'text') return item.text

  return typeName.value
})
</script>

<template>
  <article
    class="canvas-item"
    :class="{ 'canvas-item--selected': props.selected }"
    @click="emit('select')"
  >
    <header class="canvas-item__bar">
      <span class="canvas-item__handle" :title="t.actions.drag">
        <AppIcon name="grip-vertical" />
      </span>
      <button
        type="button"
        class="canvas-item__select"
        :aria-pressed="props.selected"
        :aria-label="t.itemLabel(typeName, name)"
        @click.stop="emit('select')"
      >
        <AppIcon :name="entry.icon" />
        {{ typeName }}
        <span v-if="props.item.kind === 'input' && props.item.required" class="canvas-item__tag">
          {{ t.required }}
        </span>
      </button>
      <div class="canvas-item__actions" @click.stop>
        <AppIconButton
          icon="arrow-up"
          :label="t.actions.moveUp"
          size="sm"
          :disabled="props.isFirst"
          @click="emit('moveUp')"
        />
        <AppIconButton
          icon="arrow-down"
          :label="t.actions.moveDown"
          size="sm"
          :disabled="props.isLast"
          @click="emit('moveDown')"
        />
        <AppIconButton
          icon="copy"
          :label="t.actions.duplicate"
          size="sm"
          @click="emit('duplicate')"
        />
        <AppIconButton icon="trash-2" :label="t.actions.remove" size="sm" @click="emit('remove')" />
      </div>
    </header>
    <BuilderItemPreview :item="props.item" />
  </article>
</template>

<style scoped lang="scss">
.canvas-item {
  display: grid;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4) var(--space-4);
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: border-color var(--duration-fast) var(--ease-out);

  &:hover {
    border-color: var(--color-border-strong);
  }

  &--selected,
  &--selected:hover {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 var(--border-width) var(--color-primary);
  }

  &__bar {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    min-height: var(--control-height-sm);
  }

  &__handle {
    display: grid;
    place-items: center;
    color: var(--color-text-subtle);
    cursor: grab;
  }

  &__select {
    display: inline-flex;
    flex: 1;
    gap: var(--space-2);
    align-items: center;
    min-width: 0;
    padding: 0;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
    text-align: start;
    background: none;
    border: 0;
    border-radius: var(--radius-sm);
    cursor: pointer;

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__tag {
    padding-inline: var(--space-2);
    color: var(--color-text-subtle);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-full);
  }

  // Ações aparecem no hover, na seleção e com o foco do teclado dentro do card.
  &__actions {
    display: flex;
    gap: var(--space-1);
    opacity: 0;
    transition: opacity var(--duration-fast) var(--ease-out);
  }

  &:hover &__actions,
  &--selected &__actions,
  &:focus-within &__actions {
    opacity: 1;
  }

  @media (hover: none) {
    &__actions {
      opacity: 1;
    }
  }
}
</style>
