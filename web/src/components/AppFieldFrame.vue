<script setup lang="ts">
import { common } from '@/strings'

import AppIcon from './AppIcon.vue'

/** Moldura dos campos: label, controle (slot), dica e erro. */
defineProps<{
  label: string
  inputId: string
  hintId: string
  errorId: string
  hint?: string
  error?: string
  required?: boolean
}>()
</script>

<template>
  <div class="field-frame">
    <label class="field-frame__label" :for="inputId">
      {{ label }}
      <span v-if="required" class="field-frame__required">({{ common.required }})</span>
    </label>
    <slot />
    <p v-if="hint" :id="hintId" class="field-frame__hint">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="field-frame__error">
      <AppIcon name="circle-alert" />
      {{ error }}
    </p>
  </div>
</template>

<style scoped lang="scss">
.field-frame {
  display: grid;
  gap: var(--space-2);

  &__label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--color-text);
  }

  &__required {
    margin-inline-start: var(--space-1);
    font-weight: var(--font-weight-regular);
    color: var(--color-text-subtle);
  }

  &__hint,
  &__error {
    font-size: var(--font-size-xs);
    line-height: var(--line-height-base);
  }

  &__hint {
    color: var(--color-text-muted);
  }

  &__error {
    display: flex;
    gap: var(--space-1);
    align-items: center;
    color: var(--color-danger);
  }
}
</style>
