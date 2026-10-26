<script setup lang="ts">
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import { BRAND_NAME } from '@/config/brand'

/** Logotipo em texto, com link para o início. */
defineProps<{
  to: RouteLocationRaw
}>()
</script>

<template>
  <RouterLink :to="to" class="app-brand">
    <span class="app-brand__mark" aria-hidden="true" />
    {{ BRAND_NAME }}
  </RouterLink>
</template>

<style scoped lang="scss">
.app-brand {
  display: inline-flex;
  gap: var(--space-2);
  align-items: center;
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  letter-spacing: var(--letter-spacing-tight);
  color: var(--color-text);
  text-decoration: none;
  text-transform: lowercase;

  &__mark {
    width: var(--space-3);
    height: var(--space-3);
    background: var(--color-primary);
    border-radius: var(--radius-sm);
    transform: rotate(45deg);
  }
}
</style>
