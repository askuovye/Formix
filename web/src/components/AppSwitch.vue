<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui'

import { useFieldIds } from '@/composables/useFieldIds'

const model = defineModel<boolean>({ default: false })

const props = defineProps<{
  label: string
  hint?: string
  disabled?: boolean
}>()

const { inputId, hintId, describedBy } = useFieldIds(props)
</script>

<template>
  <div class="switch" :class="{ 'switch--disabled': props.disabled }">
    <span class="switch__text">
      <label :for="inputId" class="switch__label">{{ props.label }}</label>
      <span v-if="props.hint" :id="hintId" class="switch__hint">{{ props.hint }}</span>
    </span>
    <SwitchRoot
      :id="inputId"
      v-model="model"
      class="switch__root"
      :disabled="props.disabled"
      :aria-describedby="describedBy"
    >
      <SwitchThumb class="switch__thumb" />
    </SwitchRoot>
  </div>
</template>

<style scoped lang="scss">
.switch {
  display: flex;
  gap: var(--space-4);
  align-items: center;
  justify-content: space-between;

  &--disabled {
    opacity: var(--opacity-disabled);
  }

  &__text {
    display: grid;
    gap: var(--space-1);
  }

  &__label {
    font-size: var(--font-size-sm);
    cursor: pointer;
  }

  &--disabled &__label {
    cursor: not-allowed;
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__root {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    width: var(--control-switch-width);
    height: var(--control-switch-height);
    padding: var(--border-width);
    background: var(--color-border-control);
    border: 0;
    border-radius: var(--radius-full);
    cursor: pointer;
    transition: background-color var(--duration-base) var(--ease-out);

    &[data-state='checked'] {
      background: var(--color-primary);
    }

    &:disabled {
      cursor: not-allowed;
    }
  }

  &__thumb {
    display: block;
    width: calc(var(--control-switch-height) - 2 * var(--border-width));
    height: calc(var(--control-switch-height) - 2 * var(--border-width));
    background: var(--color-surface);
    border-radius: var(--radius-full);
    box-shadow: var(--shadow-sm);
    transition: transform var(--duration-base) var(--ease-out);

    &[data-state='checked'] {
      transform: translateX(calc(var(--control-switch-width) - var(--control-switch-height)));
    }
  }
}
</style>
