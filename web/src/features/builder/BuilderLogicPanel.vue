<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'

import { useBuilderStore } from './useBuilderStore'
import { VISIBILITY_LIMITS, describeVisibility } from './visibility'

/** Visão geral das condições do formulário; clicar seleciona o campo para editar. */
const emit = defineEmits<{
  /** Um campo foi escolhido para editar a condição. */
  picked: []
}>()

const store = useBuilderStore()
const t = builder.logic

const entries = computed(() =>
  store.items.flatMap((item) =>
    item.kind === 'input' && item.visibility
      ? [
          {
            id: item.id,
            label: item.label.trim() || t.unnamed,
            description: describeVisibility(item.visibility, store.items),
            error: store.errorsFor(item.id).visibility,
          },
        ]
      : [],
  ),
)

function pick(id: string): void {
  store.select(id)
  emit('picked')
}
</script>

<template>
  <div class="logic-panel">
    <h2 class="logic-panel__title">{{ t.title }}</h2>
    <p class="logic-panel__intro">{{ t.intro }}</p>

    <ul v-if="entries.length > 0" class="logic-panel__list">
      <li v-for="entry in entries" :key="entry.id">
        <button
          type="button"
          class="logic-panel__entry"
          :class="{
            'logic-panel__entry--selected': store.selectedId === entry.id,
            'logic-panel__entry--invalid': entry.error,
          }"
          :aria-label="t.edit(entry.label)"
          @click="pick(entry.id)"
        >
          <span class="logic-panel__label">{{ entry.label }}</span>
          <span class="logic-panel__description">
            <AppIcon name="git-branch" />
            {{ entry.description }}
          </span>
          <span v-if="entry.error" class="logic-panel__error">
            <AppIcon name="circle-alert" />
            {{ entry.error }}
          </span>
        </button>
      </li>
    </ul>

    <div v-else class="logic-panel__empty">
      <p>{{ t.empty }}</p>
      <p class="logic-panel__hint">{{ t.emptyHint }}</p>
    </div>

    <p class="logic-panel__count">{{ t.count(entries.length, VISIBILITY_LIMITS.maxRules) }}</p>
  </div>
</template>

<style scoped lang="scss">
.logic-panel {
  display: grid;
  gap: var(--space-3);
  align-content: start;

  &__title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
  }

  &__intro,
  &__hint,
  &__count {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__list {
    display: grid;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__entry {
    display: grid;
    gap: var(--space-1);
    width: 100%;
    padding: var(--space-3);
    font: inherit;
    color: var(--color-text);
    text-align: start;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: border-color var(--duration-fast) var(--ease-out);

    &:hover {
      border-color: var(--color-border-strong);
    }

    &:focus-visible {
      @include focus-ring;
    }

    &--selected,
    &--selected:hover {
      border-color: var(--color-primary);
    }

    &--invalid,
    &--invalid:hover {
      border-color: var(--color-danger);
    }
  }

  &__label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__description,
  &__error {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-xs);
  }

  &__description {
    color: var(--color-text-muted);
  }

  &__error {
    color: var(--color-danger);
  }

  &__empty {
    display: grid;
    gap: var(--space-1);
    font-size: var(--font-size-sm);
  }
}
</style>
