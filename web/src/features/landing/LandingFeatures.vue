<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { landing } from '@/strings'

/** Recursos em colunas: ícone, título e uma frase. Alvo de "Recursos" no topo. */
const t = landing.features
</script>

<template>
  <section id="recursos" class="landing-features" aria-labelledby="landing-features-title">
    <h2 id="landing-features-title" class="landing-features__title">{{ t.title }}</h2>
    <ul class="landing-features__list">
      <li v-for="item in t.items" :key="item.title" class="landing-features__item js-feature">
        <span class="landing-features__icon">
          <AppIcon :name="item.icon" />
        </span>
        <h3 class="landing-features__name">{{ item.title }}</h3>
        <p class="landing-features__text">{{ item.text }}</p>
      </li>
    </ul>
  </section>
</template>

<style scoped lang="scss">
.landing-features {
  padding-block: var(--space-8) var(--space-16);
  scroll-margin-block-start: var(--space-8);

  // O título existe para leitores de tela; na tela, os itens falam por si (como na referência).
  &__title {
    @include visually-hidden;
  }

  &__list {
    display: grid;
    gap: var(--space-8) var(--space-6);
    padding: 0;
    margin: 0;
    list-style: none;

    @include up(sm) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @include up(lg) {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }
  }

  &__item {
    display: grid;
    gap: var(--space-2);
    align-content: start;
  }

  &__icon {
    display: grid;
    place-items: center;
    inline-size: var(--space-10);
    block-size: var(--space-10);
    margin-block-end: var(--space-2);
    font-size: var(--font-size-lg);
    color: var(--color-text);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__name {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
  }

  &__text {
    font-size: var(--font-size-sm);
    line-height: var(--line-height-base);
    color: var(--color-text-muted);
  }
}
</style>
