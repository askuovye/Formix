<script setup lang="ts">
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import { BRAND_NAME } from '@/config/brand'

/**
 * Logotipo (símbolo + nome), com link para o início. O símbolo é um cartão com
 * duas linhas de resposta; o mesmo desenho está em public/favicon.svg.
 */
defineProps<{
  to: RouteLocationRaw
}>()
</script>

<template>
  <RouterLink :to="to" class="app-brand">
    <svg class="app-brand__mark" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        fill-rule="evenodd"
        d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 5.5a1.5 1.5 0 0 0 0 3h10a1.5 1.5 0 0 0 0-3H7Zm0 6a1.5 1.5 0 0 0 0 3h5a1.5 1.5 0 0 0 0-3H7Z"
      />
    </svg>
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
    flex: none;
    width: var(--space-6);
    height: var(--space-6);
    fill: var(--color-primary);
  }
}
</style>
