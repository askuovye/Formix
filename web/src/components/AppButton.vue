<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import { common } from '@/strings'

import AppIcon from './AppIcon.vue'
import AppSpinner from './AppSpinner.vue'
import type { IconName } from './icons/iconNames'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    size?: 'sm' | 'md'
    type?: 'button' | 'submit' | 'reset'
    /** Com `to`, vira um link de rota com aparência de botão. */
    to?: RouteLocationRaw
    iconLeft?: IconName
    iconRight?: IconName
    loading?: boolean
    disabled?: boolean
    /** Ocupa a largura toda do contêiner. */
    block?: boolean
  }>(),
  { variant: 'primary', size: 'md', type: 'button' },
)

const isInactive = computed(() => props.disabled || props.loading)
</script>

<template>
  <RouterLink
    v-if="props.to"
    :to="props.to"
    class="app-button"
    :class="[
      `app-button--${props.variant}`,
      `app-button--${props.size}`,
      { 'app-button--block': props.block },
    ]"
  >
    <AppIcon v-if="props.iconLeft" :name="props.iconLeft" />
    <slot />
    <AppIcon v-if="props.iconRight" :name="props.iconRight" />
  </RouterLink>
  <button
    v-else
    :type="props.type"
    class="app-button"
    :class="[
      `app-button--${props.variant}`,
      `app-button--${props.size}`,
      { 'app-button--block': props.block, 'app-button--loading': props.loading },
    ]"
    :disabled="isInactive"
    :aria-busy="props.loading || undefined"
  >
    <AppSpinner v-if="props.loading" :label="common.loading" />
    <AppIcon v-else-if="props.iconLeft" :name="props.iconLeft" />
    <slot />
    <AppIcon v-if="props.iconRight && !props.loading" :name="props.iconRight" />
  </button>
</template>

<style scoped lang="scss">
.app-button {
  display: inline-flex;
  gap: var(--space-2);
  align-items: center;
  justify-content: center;
  border: var(--border-width) solid transparent;
  border-radius: var(--radius-md);
  font-weight: var(--font-weight-medium);
  line-height: var(--line-height-none);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
  transition:
    background-color var(--duration-fast) var(--ease-out),
    border-color var(--duration-fast) var(--ease-out),
    color var(--duration-fast) var(--ease-out),
    transform var(--duration-fast) var(--ease-out);

  &:active:not(:disabled) {
    transform: scale(var(--scale-pressed));
  }

  &:disabled {
    cursor: not-allowed;
    opacity: var(--opacity-disabled);
  }

  &--loading:disabled {
    cursor: progress;
  }

  &--sm {
    height: var(--control-height-sm);
    padding-inline: var(--control-padding-x-sm);
    font-size: var(--font-size-sm);
  }

  &--md {
    height: var(--control-height-md);
    padding-inline: var(--control-padding-x-md);
    font-size: var(--font-size-sm);
  }

  &--block {
    width: 100%;
  }

  &--primary {
    color: var(--color-primary-contrast);
    background: var(--color-primary);

    &:hover:not(:disabled) {
      background: var(--color-primary-hover);
    }
  }

  &--secondary {
    color: var(--color-text);
    background: var(--color-surface-raised);
    border-color: var(--color-border);

    &:hover:not(:disabled) {
      background: var(--color-surface-hover);
      border-color: var(--color-border-strong);
    }
  }

  &--ghost {
    color: var(--color-text-muted);
    background: transparent;

    &:hover:not(:disabled) {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }
  }

  &--danger {
    color: var(--color-danger);
    background: var(--color-danger-soft);

    &:hover:not(:disabled) {
      border-color: var(--color-danger);
    }
  }
}
</style>
