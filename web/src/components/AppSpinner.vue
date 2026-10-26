<script setup lang="ts">
import AppIcon from './AppIcon.vue'

/** Indicador de carregamento. Com `label`, é anunciado; sem, é decorativo. */
defineProps<{
  label?: string
}>()
</script>

<template>
  <AppIcon name="loader-circle" class="app-spinner" :label="label" />
</template>

<style scoped lang="scss">
.app-spinner {
  animation: app-spinner-spin var(--duration-spin) linear infinite;
}

@keyframes app-spinner-spin {
  to {
    transform: rotate(1turn);
  }
}
</style>
