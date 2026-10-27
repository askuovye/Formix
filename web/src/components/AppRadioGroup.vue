<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'
import { common } from '@/strings'

import AppIcon from './AppIcon.vue'

export interface RadioOption {
  value: string
  label: string
  hint?: string
  disabled?: boolean
}

const model = defineModel<string | undefined>()

const props = defineProps<{
  label: string
  options: readonly RadioOption[]
  hint?: string
  error?: string
  required?: boolean
  disabled?: boolean
}>()

const { inputId: name, hintId, errorId, describedBy } = useFieldIds(props)

function optionId(index: number): string {
  return `${name}-${index}`
}
</script>

<template>
  <fieldset
    class="radio-group"
    :disabled="props.disabled"
    :aria-describedby="describedBy"
    :aria-invalid="props.error ? 'true' : undefined"
  >
    <legend class="radio-group__legend">
      {{ props.label }}
      <span v-if="props.required" class="radio-group__required">({{ common.required }})</span>
    </legend>
    <p v-if="props.hint" :id="hintId" class="radio-group__hint">{{ props.hint }}</p>

    <div class="radio-group__options">
      <div
        v-for="(option, index) in props.options"
        :key="option.value"
        class="radio-group__option"
        :class="{ 'radio-group__option--disabled': props.disabled || option.disabled }"
      >
        <input
          :id="optionId(index)"
          v-model="model"
          type="radio"
          class="radio-group__input"
          :name="name"
          :value="option.value"
          :required="props.required"
          :disabled="option.disabled"
          :aria-describedby="option.hint ? `${optionId(index)}-hint` : undefined"
        />
        <span class="radio-group__text">
          <label :for="optionId(index)" class="radio-group__label">{{ option.label }}</label>
          <span v-if="option.hint" :id="`${optionId(index)}-hint`" class="radio-group__option-hint">
            {{ option.hint }}
          </span>
        </span>
      </div>
    </div>

    <p v-if="props.error" :id="errorId" class="radio-group__error">
      <AppIcon name="circle-alert" />
      {{ props.error }}
    </p>
  </fieldset>
</template>

<style scoped lang="scss">
.radio-group {
  display: grid;
  gap: var(--space-2);
  min-width: 0;
  padding: 0;
  border: 0;

  &__legend {
    padding: 0;
    margin-block-end: var(--space-1);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__required {
    margin-inline-start: var(--space-1);
    font-weight: var(--font-weight-regular);
    color: var(--color-text-subtle);
  }

  &__hint,
  &__option-hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__options {
    display: grid;
    gap: var(--space-3);
  }

  &__option {
    display: flex;
    gap: var(--space-3);
    align-items: flex-start;

    &--disabled {
      opacity: var(--opacity-disabled);
    }
  }

  &__input {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: var(--control-check-size);
    height: var(--control-check-size);
    margin: 0;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border-control);
    border-radius: var(--radius-full);
    cursor: pointer;
    appearance: none;
    transition: border-color var(--duration-fast) var(--ease-out);

    &::before {
      width: 50%;
      height: 50%;
      content: '';
      background: var(--color-primary);
      border-radius: var(--radius-full);
      transform: scale(0);
      transition: transform var(--duration-fast) var(--ease-out);
    }

    &:checked {
      border-color: var(--color-primary);
    }

    &:checked::before {
      transform: scale(1);
    }

    &:disabled {
      cursor: not-allowed;
    }
  }

  &[aria-invalid='true'] &__input {
    border-color: var(--color-danger);
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

  &__option--disabled &__label {
    cursor: not-allowed;
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
