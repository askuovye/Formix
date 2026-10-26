<script setup lang="ts">
import { computed, useId } from 'vue'

import type { ScaleField } from '@/types/schema'

import AnswerGroup from './AnswerGroup.vue'

/** Escala numérica (ex.: 0 a 10) em botões de opção nativos, com os rótulos das pontas. */
const props = defineProps<{
  field: ScaleField
  error?: string
}>()

const model = defineModel<number | null>({ default: null })

const name = useId()
const steps = computed(() => {
  const { min, max } = props.field.scale
  return Array.from({ length: max - min + 1 }, (_, i) => min + i)
})
</script>

<template>
  <AnswerGroup
    :label="props.field.label"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
  >
    <div class="scale">
      <div class="scale__steps">
        <label
          v-for="step in steps"
          :key="step"
          class="scale__step"
          :class="{ 'scale__step--selected': model === step }"
        >
          <input
            v-model="model"
            type="radio"
            class="scale__input"
            :name="name"
            :value="step"
            :aria-invalid="props.error ? 'true' : undefined"
          />
          {{ step }}
        </label>
      </div>
      <div v-if="props.field.scale.min_label || props.field.scale.max_label" class="scale__labels">
        <span>{{ props.field.scale.min_label }}</span>
        <span>{{ props.field.scale.max_label }}</span>
      </div>
    </div>
  </AnswerGroup>
</template>

<style scoped lang="scss">
.scale {
  display: grid;
  gap: var(--space-2);

  // Colunas iguais: numa tela estreita a escala quebra em linhas sem esticar a última.
  &__steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(var(--control-height-md), 1fr));
    gap: var(--space-1);
  }

  &__step {
    display: grid;
    place-items: center;
    height: var(--control-height-md);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
    transition:
      background-color var(--duration-fast) var(--ease-out),
      border-color var(--duration-fast) var(--ease-out),
      color var(--duration-fast) var(--ease-out);

    &:hover {
      border-color: var(--color-border-strong);
    }

    &:has(:focus-visible) {
      @include focus-ring;
    }

    &--selected,
    &--selected:hover {
      color: var(--color-primary-contrast);
      background: var(--color-primary);
      border-color: var(--color-primary);
    }
  }

  &__input {
    @include visually-hidden;
  }

  &__labels {
    display: flex;
    gap: var(--space-4);
    justify-content: space-between;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }
}
</style>
