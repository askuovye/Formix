<script setup lang="ts">
import AppButton from '@/components/AppButton.vue'
import { landing } from '@/strings'

import LandingFormMock from './LandingFormMock.vue'

/** Abertura: título em três linhas (as duas últimas em tom secundário), texto e chamadas. */
const t = landing.hero
</script>

<template>
  <section class="landing-hero" aria-labelledby="landing-title">
    <div class="landing-hero__copy">
      <h1 id="landing-title" class="landing-hero__title">
        <!-- Espaço entre as linhas: leitores de tela leem a frase inteira. -->
        <template v-for="(line, index) in t.title" :key="line">
          <span
            class="landing-hero__line js-hero-line"
            :class="{ 'landing-hero__line--muted': index > 0 }"
          >
            {{ line }}
          </span>
          {{ ' ' }}
        </template>
      </h1>
      <p class="landing-hero__text js-hero-fade">{{ t.text }}</p>
      <div class="landing-hero__actions js-hero-fade">
        <AppButton :to="{ name: 'register' }">{{ t.start }}</AppButton>
        <AppButton :to="{ hash: '#como-funciona' }" variant="ghost" icon-right="arrow-right">
          {{ t.howItWorks }}
        </AppButton>
      </div>
    </div>

    <LandingFormMock class="landing-hero__visual" />
  </section>
</template>

<style scoped lang="scss">
.landing-hero {
  display: grid;
  gap: var(--space-12);
  align-items: center;
  padding-block: var(--space-12) var(--space-16);

  @include up(lg) {
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
    padding-block: var(--space-20) var(--space-24);
  }

  &__copy {
    display: grid;
    gap: var(--space-6);
    justify-items: start;
  }

  &__title {
    display: grid;
    font-size: var(--font-size-display);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-display);
    letter-spacing: var(--letter-spacing-tight);
  }

  &__line {
    display: block;

    &--muted {
      color: var(--color-text-subtle);
    }
  }

  &__text {
    max-inline-size: var(--layout-width-text);
    font-size: var(--font-size-lg);
    line-height: var(--line-height-base);
    color: var(--color-text-muted);
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
  }

  &__visual {
    justify-self: center;
  }
}
</style>
