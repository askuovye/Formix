<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'

import AppAlert from '@/components/AppAlert.vue'
import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'
import type { SchemaItem } from '@/types/schema'

import BuilderCanvasItem from './BuilderCanvasItem.vue'
import { useCanvasDevice } from './canvasDevice'
import { GENERAL_ERRORS } from './schemaErrors'
import { describeVisibility } from './visibility'
import { BUILDER_DRAG_GROUP, SORTABLE_OPTIONS, sortableAnimationMs } from './dragAndDrop'
import { useDragLift } from './dragLift'
import { useBuilderStore } from './useBuilderStore'

const store = useBuilderStore()
const t = builder.canvas
const lift = useDragLift({ themed: true, ghostClass: 'canvas__ghost' })
const { device } = useCanvasDevice()

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

/** Capa e logo do tema, para o canvas lembrar a página publicada. */
const cover = computed(() => store.schema.theme?.cover ?? null)
const logo = computed(() => store.schema.theme?.logo?.url ?? null)
const centered = computed(() => store.schema.theme?.alignment === 'center')

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
  <section class="canvas" :class="{ 'canvas--mobile': device === 'mobile' }" :aria-label="t.label">
    <div
      v-if="cover?.url || logo"
      class="canvas__brand"
      :class="{ 'canvas__brand--center': centered }"
      aria-hidden="true"
    >
      <img
        v-if="cover?.url"
        :src="cover.url"
        alt=""
        class="canvas__cover"
        :class="`canvas__cover--${cover.fit}`"
      />
      <img v-if="logo" :src="logo" alt="" class="canvas__logo" />
    </div>
    <AppAlert v-if="generalErrors.length > 0" tone="danger">
      {{ builder.save.generalError }}
      <span v-for="message in generalErrors" :key="message"> {{ message }}</span>
    </AppAlert>
    <p v-if="stepCount > 1" class="canvas__steps">{{ t.step(1, stepCount) }}</p>

    <draggable
      :model-value="store.items"
      :group="{ name: BUILDER_DRAG_GROUP, pull: false, put: true }"
      v-bind="SORTABLE_OPTIONS"
      :animation="sortableAnimationMs()"
      item-key="id"
      filter=".canvas-item__actions"
      :prevent-on-filter="false"
      tag="ol"
      class="canvas__list"
      ghost-class="canvas__ghost"
      fallback-class="canvas__fallback"
      @start="lift.start"
      @end="lift.end"
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
  // Troca de largura da prévia (computador/celular); zera com movimento reduzido.
  transition: max-width var(--duration-slow) var(--ease-out);

  &--mobile {
    max-width: var(--builder-canvas-width-mobile);
  }

  &__brand {
    display: grid;
    gap: var(--space-3);
    justify-items: start;

    &--center {
      justify-items: center;
    }
  }

  &__cover {
    inline-size: 100%;
    block-size: calc(var(--form-cover-height) / 2);
    object-fit: cover;
    border-radius: var(--radius-lg);

    &--contain {
      object-fit: contain;
    }
  }

  &__logo {
    max-inline-size: var(--form-logo-max-width);
    max-block-size: var(--form-logo-height);
    object-fit: contain;
  }

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

  // A vaga onde o card vai cair: contorno tracejado no lugar do conteúdo.
  &__ghost {
    background: var(--color-surface-hover);
    border: var(--border-width-strong) dashed var(--color-primary);
    border-radius: var(--radius-lg);

    > * {
      visibility: hidden;
    }
  }

  // Cópia do SortableJS: escondida, o card levantado é o BuilderDragLift.
  &__fallback {
    visibility: hidden;
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
