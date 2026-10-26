<script setup lang="ts">
import AppCard from '@/components/AppCard.vue'

/** Moldura das telas de autenticação: título, subtítulo, conteúdo e rodapé com links. */
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="auth-card">
    <AppCard as="section" padding="lg" class="auth-card__card" aria-labelledby="auth-card-title">
      <header class="auth-card__header">
        <h1 id="auth-card-title" class="auth-card__title">{{ title }}</h1>
        <p v-if="subtitle" class="auth-card__subtitle">{{ subtitle }}</p>
      </header>
      <slot />
    </AppCard>
    <footer v-if="$slots.footer" class="auth-card__footer">
      <slot name="footer" />
    </footer>
  </div>
</template>

<style scoped lang="scss">
.auth-card {
  display: grid;
  gap: var(--space-4);
  width: 100%;
  max-width: var(--dialog-width-md);
  margin-inline: auto;

  @include up(md) {
    margin-block-start: var(--space-8);
  }

  &__card {
    display: grid;
    gap: var(--space-6);
  }

  &__header {
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

  &__footer {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    text-align: center;
  }
}
</style>
