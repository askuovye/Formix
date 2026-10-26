<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import { builder } from '@/strings'
import type { SchemaItem } from '@/types/schema'

import BuilderItemPreview from './BuilderItemPreview.vue'
import { paletteEntryFor } from './catalog'
import type { ItemErrors } from './schemaErrors'

const props = defineProps<{
  item: SchemaItem
  selected: boolean
  isFirst: boolean
  isLast: boolean
  errors: ItemErrors
  /** Frase da condição de mostrar/ocultar, quando houver. */
  condition?: string
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
const hasErrors = computed(() => Object.keys(props.errors).length > 0)

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
    :class="{ 'canvas-item--selected': props.selected, 'canvas-item--invalid': hasErrors }"
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
    <p v-if="props.condition" class="canvas-item__condition">
      <AppIcon name="git-branch" />
      {{ props.condition }}
    </p>
    <BuilderItemPreview :item="props.item" />
    <p v-if="hasErrors" class="canvas-item__error">
      <AppIcon name="circle-alert" />
      {{ props.errors.item ?? props.errors.visibility ?? t.hasErrors }}
    </p>
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
  // O card inteiro arrasta (sem selecionar texto); os botões de ação continuam só clicáveis.
  cursor: grab;
  user-select: none;
  transition: border-color var(--duration-fast) var(--ease-out);

  &:hover {
    border-color: var(--color-border-strong);
  }

  &--selected,
  &--selected:hover {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 var(--border-width) var(--color-primary);
  }

  &--invalid,
  &--invalid:hover {
    border-color: var(--color-danger);
  }

  &--invalid#{&}--selected {
    box-shadow: 0 0 0 var(--border-width) var(--color-danger);
  }

  &__condition {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    justify-self: start;
    padding: var(--space-1) var(--space-2);
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__error {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-xs);
    color: var(--color-danger);
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
  // Celular e toque: as ações aparecem só no item selecionado (quatro botões
  // por card espremem o cabeçalho). Com mouse, a partir de sm, aparecem no
  // hover e no foco, sem deslocar o layout.
  &__actions {
    display: none;
    gap: var(--space-1);
    transition: opacity var(--duration-fast) var(--ease-out);
  }

  &--selected &__actions {
    display: flex;
  }

  @include up(sm) {
    @media (hover: hover) {
      &__actions {
        display: flex;
        opacity: 0;
      }

      &:hover &__actions,
      &--selected &__actions,
      &:focus-within &__actions {
        opacity: 1;
      }
    }
  }
}
</style>
