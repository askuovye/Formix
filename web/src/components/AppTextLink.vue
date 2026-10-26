<script setup lang="ts">
import { RouterLink, type RouteLocationRaw } from 'vue-router'

/** Link de texto (rota interna). Com `external`, abre em nova aba (ex.: Termos durante o cadastro). */
defineProps<{
  to: RouteLocationRaw
  external?: boolean
}>()
</script>

<template>
  <RouterLink
    :to="to"
    class="text-link"
    :target="external ? '_blank' : undefined"
    :rel="external ? 'noopener' : undefined"
  >
    <slot />
  </RouterLink>
</template>

<style scoped lang="scss">
.text-link {
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
  text-decoration: underline;
  text-decoration-color: var(--color-border-strong);
  text-underline-offset: var(--space-1);
  transition: text-decoration-color var(--duration-fast) var(--ease-out);

  &:hover {
    text-decoration-color: currentColor;
  }
}
</style>
