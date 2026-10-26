<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'

import AppIcon from './AppIcon.vue'

const model = defineModel<boolean>({ default: false })

const props = defineProps<{
  label: string
  hint?: string
  error?: string
  disabled?: boolean
}>()

/** O slot `label` substitui o texto do label (ex.: texto com links). */
defineSlots<{ label?: () => unknown }>()

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)
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
        :aria-invalid="props.error ? 'true' : undefined"
        :aria-describedby="describedBy"
      />
      <AppIcon name="check" class="checkbox__mark" />
    </span>
    <span class="checkbox__text">
      <label :for="inputId" class="checkbox__label">
        <slot name="label">{{ props.label }}</slot>
      </label>
      <span v-if="props.hint" :id="hintId" class="checkbox__hint">{{ props.hint }}</span>
      <span v-if="props.error" :id="errorId" class="checkbox__error">
        <AppIcon name="circle-alert" />
        {{ props.error }}
      </span>
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

  &__input[aria-invalid='true'] {
    border-color: var(--color-danger);
  }

  &__error {
    display: flex;
    gap: var(--space-1);
    align-items: center;
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }
}
</style>
