<script setup lang="ts">
withDefaults(
  defineProps<{
    tone?: 'neutral' | 'success' | 'warning' | 'danger'
  }>(),
  { tone: 'neutral' },
)
</script>

<template>
  <span class="app-badge" :class="`app-badge--${tone}`">
    <slot />
  </span>
</template>

<style scoped lang="scss">
.app-badge {
  display: inline-flex;
  align-items: center;
  height: var(--space-6);
  padding-inline: var(--space-2);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  line-height: var(--line-height-none);
  white-space: nowrap;
  border-radius: var(--radius-sm);

  &--neutral {
    color: var(--color-text-muted);
    background: var(--color-surface-hover);
  }

  &--success {
    color: var(--color-success);
    background: var(--color-success-soft);
  }

  &--warning {
    color: var(--color-warning);
    background: var(--color-warning-soft);
  }

  &--danger {
    color: var(--color-danger);
    background: var(--color-danger-soft);
  }
}
</style>
