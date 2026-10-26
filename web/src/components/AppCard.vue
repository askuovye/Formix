<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Elemento raiz: use `section`/`article` quando o card tiver título próprio. */
    as?: 'div' | 'section' | 'article'
    padding?: 'none' | 'md' | 'lg'
    /** Realce de hover para cards clicáveis. */
    interactive?: boolean
  }>(),
  { as: 'div', padding: 'md' },
)
</script>

<template>
  <component
    :is="as"
    class="app-card"
    :class="[`app-card--padding-${padding}`, { 'app-card--interactive': interactive }]"
  >
    <slot />
  </component>
</template>

<style scoped lang="scss">
.app-card {
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);

  &--padding-md {
    padding: var(--space-4);
  }

  &--padding-lg {
    padding: var(--space-6);
  }

  &--interactive {
    transition:
      border-color var(--duration-fast) var(--ease-out),
      background-color var(--duration-fast) var(--ease-out);

    &:hover {
      background: var(--color-surface-raised);
      border-color: var(--color-border-strong);
    }
  }
}
</style>
