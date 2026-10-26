<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'

import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'

import { PALETTE, type PaletteEntry, type PaletteGroup } from './catalog'
import { BUILDER_DRAG_GROUP, SORTABLE_OPTIONS } from './dragAndDrop'
import { useDragLift } from './dragLift'
import { useBuilderStore } from './useBuilderStore'

const props = defineProps<{
  /** Campos (básicos e avançados) ou elementos de organização. */
  section: 'fields' | 'elements'
}>()

const emit = defineEmits<{
  /** Um item foi adicionado pelo clique (a gaveta do celular fecha). */
  added: []
}>()

const store = useBuilderStore()
const t = builder.palette
const lift = useDragLift({ themed: false, ghostClass: 'palette__ghost' })

const GROUPS: Record<typeof props.section, PaletteGroup[]> = {
  fields: ['basic', 'advanced'],
  elements: ['element'],
}
const groups = computed(() => GROUPS[props.section])
const entriesByGroup = Object.fromEntries(
  (['basic', 'advanced', 'element'] as const).map((group) => [
    group,
    PALETTE.filter((entry) => entry.group === group),
  ]),
) as Record<PaletteGroup, PaletteEntry[]>

function add(entry: PaletteEntry): void {
  store.add(entry.key)
  emit('added')
}

// Ao arrastar para o canvas, a cópia levada já é o item novo, com id livre.
function cloneEntry(entry: PaletteEntry) {
  return store.createItem(entry.key)
}
</script>

<template>
  <div class="palette">
    <h2 class="palette__title">{{ builder.rail.sections[props.section] }}</h2>
    <p class="palette__hint">{{ builder.rail.hints[props.section] }}</p>

    <section v-for="group in groups" :key="group" class="palette__group">
      <h3 v-if="groups.length > 1" class="palette__group-title">{{ t.groups[group] }}</h3>
      <draggable
        :list="entriesByGroup[group]"
        :group="{ name: BUILDER_DRAG_GROUP, pull: 'clone', put: false }"
        :sort="false"
        :clone="cloneEntry"
        v-bind="SORTABLE_OPTIONS"
        ghost-class="palette__ghost"
        fallback-class="palette__fallback"
        item-key="key"
        tag="ul"
        class="palette__list"
        @start="lift.start"
        @end="lift.end"
      >
        <template #item="{ element }: { element: PaletteEntry }">
          <li>
            <button
              type="button"
              class="palette__item"
              :aria-label="t.add(builder.types[element.key])"
              @click="add(element)"
            >
              <AppIcon :name="element.icon" class="palette__icon" />
              {{ builder.types[element.key] }}
            </button>
          </li>
        </template>
      </draggable>
    </section>
  </div>
</template>

<style scoped lang="scss">
.palette {
  display: grid;
  gap: var(--space-4);
  align-content: start;

  &__title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__group {
    display: grid;
    gap: var(--space-2);
  }

  &__group-title {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__list {
    display: grid;
    gap: var(--space-1);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__item {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    width: 100%;
    height: var(--control-height-md);
    padding-inline: var(--space-3);
    font-size: var(--font-size-sm);
    color: var(--color-text);
    text-align: start;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: grab;
    transition:
      background-color var(--duration-fast) var(--ease-out),
      border-color var(--duration-fast) var(--ease-out);

    &:hover {
      background: var(--color-surface-hover);
      border-color: var(--color-border-strong);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__fallback {
    visibility: hidden;
  }

  &__icon {
    color: var(--color-text-muted);
  }
}

// A vaga do item: contorno tracejado. Sobre o canvas, o SortableJS troca a
// classe pela do destino (canvas__ghost), que precisa valer aqui também.
.palette__ghost,
.canvas__ghost {
  min-block-size: var(--control-height-md);
  background: var(--color-surface-hover);
  border: var(--border-width-strong) dashed var(--color-primary);
  border-radius: var(--radius-md);

  > * {
    visibility: hidden;
  }
}
</style>
