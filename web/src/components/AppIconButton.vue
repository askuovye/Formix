<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

const props = withDefaults(
  defineProps<{
    icon: IconName
    /** Obrigatório: é o nome acessível do botão. */
    label: string
    variant?: 'ghost' | 'secondary'
    size?: 'sm' | 'md'
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
  }>(),
  { variant: 'ghost', size: 'md', type: 'button' },
)
</script>

<template>
  <button
    :type="props.type"
    class="app-icon-button"
    :class="[`app-icon-button--${props.variant}`, `app-icon-button--${props.size}`]"
    :aria-label="props.label"
    :disabled="props.disabled"
  >
    <AppIcon :name="props.icon" />
  </button>
</template>

<style scoped lang="scss">
.app-icon-button {
  display: inline-grid;
  place-items: center;
  padding: 0;
  color: var(--color-text-muted);
  background: transparent;
  border: var(--border-width) solid transparent;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition:
    background-color var(--duration-fast) var(--ease-out),
    color var(--duration-fast) var(--ease-out);

  &:hover:not(:disabled) {
    color: var(--color-text);
    background: var(--color-surface-hover);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: var(--opacity-disabled);
  }

  &--sm {
    width: var(--control-height-sm);
    height: var(--control-height-sm);
    font-size: var(--font-size-md);
  }

  &--md {
    width: var(--control-height-md);
    height: var(--control-height-md);
    font-size: var(--font-size-lg);
  }

  &--secondary {
    background: var(--color-surface-raised);
    border-color: var(--color-border);
  }
}
</style>
