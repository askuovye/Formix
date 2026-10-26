<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'

import { BUILDER_SECTIONS, type BuilderSection } from './sections'

/** Seções do editor. Vertical na coluna da esquerda; horizontal na gaveta do celular. */
const props = withDefaults(
  defineProps<{
    orientation?: 'vertical' | 'horizontal'
  }>(),
  { orientation: 'vertical' },
)

const section = defineModel<BuilderSection>({ required: true })
const t = builder.rail
</script>

<template>
  <nav class="rail" :class="`rail--${props.orientation}`" :aria-label="t.label">
    <ul class="rail__list">
      <li v-for="entry in BUILDER_SECTIONS" :key="entry.key">
        <button
          type="button"
          class="rail__item"
          :class="{ 'rail__item--active': section === entry.key }"
          :aria-pressed="section === entry.key"
          :disabled="entry.soon"
          @click="section = entry.key"
        >
          <AppIcon :name="entry.icon" class="rail__icon" />
          <span class="rail__label">{{ t.sections[entry.key] }}</span>
          <span v-if="entry.soon" class="rail__soon">{{ t.soon }}</span>
        </button>
      </li>
    </ul>
  </nav>
</template>

<style scoped lang="scss">
.rail {
  &__list {
    display: grid;
    gap: var(--space-1);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &--horizontal &__list {
    grid-auto-columns: minmax(0, 1fr);
    grid-auto-flow: column;
  }

  &__item {
    display: grid;
    gap: var(--space-1);
    justify-items: center;
    width: 100%;
    padding: var(--space-2) 0;
    font: inherit;
    color: var(--color-text-muted);
    text-align: center;
    background: transparent;
    border: 0;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition:
      color var(--duration-fast) var(--ease-out),
      background-color var(--duration-fast) var(--ease-out);

    &:hover:not(:disabled) {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }

    &:focus-visible {
      @include focus-ring;
    }

    &:disabled {
      cursor: not-allowed;
      opacity: var(--opacity-disabled);
    }

    &--active {
      color: var(--color-text);
      background: var(--color-surface-raised);
    }
  }

  &__icon {
    font-size: var(--font-size-lg);
  }

  &__label {
    max-width: 100%;
    font-size: var(--font-size-2xs);
    font-weight: var(--font-weight-medium);
    line-height: var(--line-height-tight);
  }

  &__soon {
    font-size: var(--font-size-2xs);
    line-height: var(--line-height-tight);
  }
}
</style>
