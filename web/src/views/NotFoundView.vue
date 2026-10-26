<script setup lang="ts">
import AppButton from '@/components/AppButton.vue'
import { notFound } from '@/strings'
</script>

<template>
  <section class="not-found">
    <p class="not-found__code" aria-hidden="true">{{ notFound.code }}</p>
    <h1 class="not-found__title">{{ notFound.title }}</h1>
    <p class="not-found__text">{{ notFound.text }}</p>
    <AppButton to="/" icon-left="arrow-left">{{ notFound.back }}</AppButton>
  </section>
</template>

<style scoped lang="scss">
.not-found {
  display: grid;
  gap: var(--space-4);
  justify-items: center;
  padding-block: var(--space-16);
  text-align: center;

  &__code {
    font-size: var(--font-size-4xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-none);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-subtle);
  }

  &__title {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-semibold);
  }

  &__text {
    max-width: var(--dialog-width-md);
    margin-block-end: var(--space-4);
    color: var(--color-text-muted);
  }
}
</style>
