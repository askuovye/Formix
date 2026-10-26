<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'

import AppIcon from './AppIcon.vue'

const model = defineModel<boolean>({ default: false })

const props = defineProps<{
  label: string
  hint?: string
  disabled?: boolean
}>()

const { inputId, hintId, describedBy } = useFieldIds(props)
</script>

<template>
  <div class="checkbox" :class="{ 'checkbox--disabled': props.disabled }">
    <span class="checkbox__control">
      <input
        :id="inputId"
        v-model="model"
        type="checkbox"
        class="checkbox__input"
        :disabled="props.disabled"
        :aria-describedby="describedBy"
      />
      <AppIcon name="check" class="checkbox__mark" />
    </span>
    <span class="checkbox__text">
      <label :for="inputId" class="checkbox__label">{{ props.label }}</label>
      <span v-if="props.hint" :id="hintId" class="checkbox__hint">{{ props.hint }}</span>
    </span>
  </div>
</template>

<style scoped lang="scss">
.checkbox {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;

  &--disabled {
    opacity: var(--opacity-disabled);
  }

  &__control {
    position: relative;
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: var(--control-check-size);
    height: var(--control-check-size);
  }

  &__input {
    width: 100%;
    height: 100%;
    margin: 0;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: var(--radius-sm);
    cursor: pointer;
    appearance: none;
    transition:
      background-color var(--duration-fast) var(--ease-out),
      border-color var(--duration-fast) var(--ease-out);

    &:checked {
      background: var(--color-primary);
      border-color: var(--color-primary);
    }

    &:disabled {
      cursor: not-allowed;
    }
  }

  &__mark {
    position: absolute;
    font-size: var(--font-size-xs);
    color: var(--color-primary-contrast);
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--duration-fast) var(--ease-out);
  }

  &__input:checked + &__mark {
    opacity: 1;
  }

  &__text {
    display: grid;
    gap: var(--space-1);
  }

  &__label {
    font-size: var(--font-size-sm);
    line-height: var(--control-check-size);
    cursor: pointer;
  }

  &--disabled &__label {
    cursor: not-allowed;
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }
}
</style>
