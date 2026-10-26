<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'

import AppAlert from '@/components/AppAlert.vue'
import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'
import type { SchemaItem } from '@/types/schema'

import BuilderCanvasItem from './BuilderCanvasItem.vue'
import { GENERAL_ERRORS } from './schemaErrors'
import { describeVisibility } from './visibility'
import { BUILDER_DRAG_GROUP, sortableAnimationMs } from './dragAndDrop'
import { useBuilderStore } from './useBuilderStore'

const store = useBuilderStore()
const t = builder.canvas

/** Evento `change` do vuedraggable: o estado só muda pelo store (histórico de desfazer). */
type DragChange =
  | { added: { element: SchemaItem; newIndex: number } }
  | { moved: { element: SchemaItem; oldIndex: number; newIndex: number } }
  | { removed: { element: SchemaItem; oldIndex: number } }

function onChange(change: DragChange): void {
  if ('added' in change) {
    store.insert(change.added.element, change.added.newIndex)
  } else if ('moved' in change) {
    store.move(change.moved.oldIndex, change.moved.newIndex)
  }
}

const generalErrors = computed(() => Object.values(store.errorsFor(GENERAL_ERRORS)))

const stepCount = computed(
  () => store.items.filter((item) => item.type === 'page_break').length + 1,
)

/** Rótulo para avisos: rótulo do campo, texto do bloco ou nome do tipo. */
function nameOf(item: SchemaItem): string {
  if (item.kind === 'input') return item.label
  if (item.type === 'section_title' || item.type === 'text') return item.text

  return builder.types[item.type]
}

function moveBy(index: number, offset: number): void {
  const item = store.items[index]
  const to = index + offset

  if (!item || to < 0 || to >= store.items.length) return

  store.move(index, to)
  store.announce(t.moved(nameOf(item), to + 1, store.items.length))
}

function remove(item: SchemaItem): void {
  store.remove(item.id)
  store.announce(t.removed(nameOf(item)))
}
</script>

<template>
  <section class="canvas" :aria-label="t.label">
    <AppAlert v-if="generalErrors.length > 0" tone="danger">
      {{ builder.save.generalError }}
      <span v-for="message in generalErrors" :key="message"> {{ message }}</span>
    </AppAlert>
    <p v-if="stepCount > 1" class="canvas__steps">{{ t.step(1, stepCount) }}</p>

    <draggable
      :model-value="store.items"
      :group="{ name: BUILDER_DRAG_GROUP, pull: false, put: true }"
      :animation="sortableAnimationMs()"
      item-key="id"
      handle=".canvas-item__handle"
      tag="ol"
      class="canvas__list"
      ghost-class="canvas__ghost"
      @change="onChange"
    >
      <template #item="{ element, index }: { element: SchemaItem; index: number }">
        <li class="canvas__entry">
          <BuilderCanvasItem
            :item="element"
            :selected="store.selectedId === element.id"
            :is-first="index === 0"
            :is-last="index === store.items.length - 1"
            :errors="store.errorsFor(element.id)"
            :condition="
              element.kind === 'input' && element.visibility
                ? describeVisibility(element.visibility, store.items)
                : undefined
            "
            @select="store.select(element.id)"
            @move-up="moveBy(index, -1)"
            @move-down="moveBy(index, 1)"
            @duplicate="store.duplicate(element.id)"
            @remove="remove(element)"
          />
        </li>
      </template>
      <template #footer>
        <li v-if="store.items.length === 0" class="canvas__empty">
          <AppIcon name="plus" class="canvas__empty-icon" />
          <p class="canvas__empty-title">{{ t.emptyTitle }}</p>
          <p class="canvas__empty-text">{{ t.emptyText }}</p>
        </li>
      </template>
    </draggable>
  </section>
</template>

<style scoped lang="scss">
.canvas {
  display: grid;
  gap: var(--space-3);
  width: 100%;
  max-width: var(--builder-canvas-width);
  margin-inline: auto;

  &__steps {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__list {
    display: grid;
    gap: var(--space-3);
    min-height: calc(var(--control-height-md) * 4);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__ghost {
    opacity: var(--opacity-disabled);
  }

  &__empty {
    display: grid;
    gap: var(--space-2);
    justify-items: center;
    padding: var(--space-12) var(--space-6);
    text-align: center;
    border: var(--border-width-strong) dashed var(--color-border-strong);
    border-radius: var(--radius-lg);
  }

  &__empty-icon {
    font-size: var(--font-size-2xl);
    color: var(--color-text-subtle);
  }

  &__empty-title {
    font-weight: var(--font-weight-semibold);
  }

  &__empty-text {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
