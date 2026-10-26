<script setup lang="ts">
import AppCard from '@/components/AppCard.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import { formsPage } from '@/strings'
</script>

<template>
  <AppPageHeader :title="formsPage.title" :subtitle="formsPage.subtitle" />
  <AppCard padding="lg" class="forms-empty">
    <AppIcon name="file-text" class="forms-empty__icon" />
    <h2 class="forms-empty__title">{{ formsPage.emptyTitle }}</h2>
    <p class="forms-empty__text">{{ formsPage.emptyText }}</p>
  </AppCard>
</template>

<style scoped lang="scss">
.forms-empty {
  display: grid;
  gap: var(--space-2);
  justify-items: center;
  padding-block: var(--space-12);
  text-align: center;

  &__icon {
    font-size: var(--font-size-3xl);
    color: var(--color-text-subtle);
  }

  &__title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
  }

  &__text {
    max-width: var(--dialog-width-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
