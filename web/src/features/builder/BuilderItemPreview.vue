<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'
import type { SchemaItem } from '@/types/schema'

/**
 * Aparência aproximada do item no canvas (não interativa). A renderização de
 * verdade, com tema, é do renderer (fase 4).
 */
const props = defineProps<{
  item: SchemaItem
}>()

const t = builder.canvas

const scaleSteps = computed(() => {
  if (props.item.type !== 'scale') return []
  const { min, max } = props.item.scale

  // Enquanto a escala está inválida (campo vazio, fim antes do início), não desenha nada.
  if (!Number.isInteger(min) || !Number.isInteger(max) || max < min) return []

  return Array.from({ length: Math.min(max - min, 10) + 1 }, (_, i) => min + i)
})
</script>

<template>
  <div class="preview" aria-hidden="true">
    <template v-if="props.item.kind === 'block'">
      <p v-if="props.item.type === 'section_title'" class="preview__section">
        {{ props.item.text }}
      </p>
      <p v-else-if="props.item.type === 'text'" class="preview__text">{{ props.item.text }}</p>
      <hr v-else-if="props.item.type === 'divider'" class="preview__divider" />
      <div v-else class="preview__page-break">
        <span>{{ t.pageBreak }}</span>
      </div>
    </template>

    <template v-else>
      <p class="preview__label">
        {{ props.item.label }}
        <span v-if="props.item.required" class="preview__required">*</span>
      </p>
      <p v-if="props.item.help_text" class="preview__help">{{ props.item.help_text }}</p>

      <ul
        v-if="
          props.item.type === 'multiple_choice' ||
          (props.item.type === 'single_choice' && props.item.display !== 'select')
        "
        class="preview__options"
      >
        <li v-for="option in props.item.options" :key="option.value" class="preview__option">
          <span
            class="preview__mark"
            :class="{ 'preview__mark--round': props.item.type === 'single_choice' }"
          />
          {{ option.label }}
        </li>
      </ul>

      <div v-else-if="props.item.type === 'single_choice'" class="preview__control">
        <span>{{ props.item.options[0]?.label }}</span>
        <AppIcon name="chevron-down" />
      </div>

      <div v-else-if="props.item.type === 'rating'" class="preview__stars">
        <AppIcon v-for="n in props.item.rating.max" :key="n" name="star" />
      </div>

      <div v-else-if="props.item.type === 'scale'" class="preview__scale">
        <div class="preview__scale-steps">
          <span v-for="step in scaleSteps" :key="step" class="preview__scale-step">{{ step }}</span>
        </div>
        <div
          v-if="props.item.scale.min_label || props.item.scale.max_label"
          class="preview__scale-labels"
        >
          <span>{{ props.item.scale.min_label }}</span>
          <span>{{ props.item.scale.max_label }}</span>
        </div>
      </div>

      <div v-else-if="props.item.type === 'file'" class="preview__file">
        <AppIcon name="upload" />
        {{ t.filePlaceholder }}
      </div>

      <div
        v-else
        class="preview__control"
        :class="{ 'preview__control--tall': props.item.type === 'long_text' }"
      >
        <span>{{
          ('placeholder' in props.item && props.item.placeholder) || t.answerPlaceholder
        }}</span>
        <AppIcon v-if="props.item.type === 'date'" name="calendar" />
      </div>
    </template>
  </div>
</template>

<style scoped lang="scss">
.preview {
  display: grid;
  gap: var(--space-2);

  &__label {
    font-weight: var(--font-weight-medium);
    overflow-wrap: anywhere;
  }

  &__required {
    color: var(--color-danger);
  }

  &__help {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__section {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
  }

  &__text {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  &__divider {
    width: 100%;
    margin: 0;
    border: 0;
    border-block-start: var(--border-width) solid var(--color-border-strong);
  }

  &__page-break {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);

    &::before,
    &::after {
      flex: 1;
      content: '';
      border-block-start: var(--border-width-strong) dashed var(--color-border-strong);
    }
  }

  &__control {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    justify-content: space-between;
    height: var(--control-height-md);
    padding-inline: var(--control-padding-x-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-subtle);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);

    &--tall {
      align-items: flex-start;
      height: auto;
      min-height: calc(var(--control-height-md) * 2);
      padding-block: var(--space-2);
    }
  }

  &__options {
    display: grid;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__option {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    font-size: var(--font-size-sm);
  }

  &__mark {
    width: var(--control-check-size);
    height: var(--control-check-size);
    border: var(--border-width-strong) solid var(--color-border-strong);
    border-radius: var(--radius-sm);

    &--round {
      border-radius: var(--radius-full);
    }
  }

  &__stars {
    display: flex;
    gap: var(--space-2);
    font-size: var(--font-size-xl);
    color: var(--color-text-subtle);
  }

  &__scale {
    display: grid;
    gap: var(--space-1);
  }

  &__scale-steps {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-1);
  }

  &__scale-step {
    display: grid;
    place-items: center;
    min-width: var(--control-height-sm);
    height: var(--control-height-sm);
    font-size: var(--font-size-sm);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-sm);
  }

  &__scale-labels {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__file {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    justify-content: center;
    padding: var(--space-6);
    font-size: var(--font-size-sm);
    color: var(--color-text-subtle);
    border: var(--border-width-strong) dashed var(--color-border-strong);
    border-radius: var(--radius-md);
  }
}
</style>
