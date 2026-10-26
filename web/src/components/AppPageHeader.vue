<script setup lang="ts">
/** Título da página, subtítulo opcional e ações à direita (slot `actions`). */
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="page-header">
    <div class="page-header__text">
      <h1 class="page-header__title">{{ title }}</h1>
      <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped lang="scss">
.page-header {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  align-items: flex-start;
  justify-content: space-between;
  margin-block-end: var(--space-8);

  &__text {
    display: grid;
    gap: var(--space-1);
  }

  &__title {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
  }

  &__subtitle {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__actions {
    display: flex;
    gap: var(--space-2);
  }
}
</style>
