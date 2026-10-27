<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { landing } from '@/strings'

/**
 * Ilustração do hero: um formulário de exemplo (tema Clássico, sempre claro)
 * sobre camadas de profundidade, e o selo de novos leads. Feito em HTML e
 * tokens, não em imagem: nítido em qualquer tela e leve. Decorativo para
 * leitores de tela (o texto do hero já diz o que é).
 */
const t = landing.mock
</script>

<template>
  <div class="form-mock" aria-hidden="true">
    <span class="form-mock__layer form-mock__layer--back js-mock-layer" />
    <span class="form-mock__layer form-mock__layer--middle js-mock-layer" />

    <div class="form-mock__card js-mock-card">
      <p class="form-mock__question">{{ t.question }}</p>
      <ul class="form-mock__options">
        <li
          v-for="(option, index) in t.options"
          :key="option"
          class="form-mock__option"
          :class="{ 'form-mock__option--checked': index === 0 }"
        >
          <span class="form-mock__radio" />
          {{ option }}
        </li>
      </ul>
      <span class="form-mock__next">
        <AppIcon name="arrow-right" />
      </span>
    </div>

    <div class="form-mock__leads js-mock-leads">
      <span class="form-mock__leads-value">{{ t.leadsValue }}</span>
      <span class="form-mock__leads-text">
        {{ t.leads }}
        <span class="form-mock__leads-period">{{ t.leadsPeriod }}</span>
      </span>
      <AppIcon name="trending-up" class="form-mock__leads-icon" />
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/styles/themes/dark';
@use '@/styles/themes/light';

.form-mock {
  position: relative;
  display: grid;
  inline-size: 100%;
  max-inline-size: var(--landing-mock-width);
  padding-block: var(--space-8);

  // Camadas atrás do cartão: superfícies sólidas levemente giradas (sem vidro nem brilho).
  // Só no desktop, junto com a inclinação do cartão (no celular vazariam pela borda).
  &__layer {
    position: absolute;
    display: none;
    inset: var(--space-8) 0;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-xl);

    @include up(lg) {
      display: block;
    }

    &--back {
      transform: translate(calc(var(--space-12) * -1), calc(var(--space-4) * -1)) rotate(-10deg);
    }

    &--middle {
      background: var(--color-surface);
      transform: translate(calc(var(--space-6) * -1), calc(var(--space-2) * -1)) rotate(-6deg);
    }
  }

  // O formulário em si: tema Clássico (claro) nos dois temas do app.
  &__card {
    @include light.values;
    @include color-roles;

    position: relative;
    display: grid;
    gap: var(--space-4);
    padding: var(--space-6);
    color: var(--color-text);
    background: var(--color-surface);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);

    // Inclinado só no desktop; no celular, reto e fácil de ler.
    @include up(lg) {
      transform: rotate(var(--landing-mock-tilt));
    }
  }

  &__question {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-tight);
  }

  &__options {
    display: grid;
    gap: var(--space-3);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__option {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);

    &--checked {
      color: var(--color-text);
    }
  }

  &__radio {
    flex: none;
    inline-size: var(--space-4);
    block-size: var(--space-4);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: var(--radius-full);

    .form-mock__option--checked & {
      border: var(--space-1) solid var(--color-primary);
    }
  }

  &__next {
    display: grid;
    place-items: center;
    justify-self: end;
    inline-size: var(--space-8);
    block-size: var(--space-8);
    color: var(--color-primary-contrast);
    background: var(--color-primary);
    border-radius: var(--radius-md);
  }

  // Selo de leads: superfície escura nos dois temas, sobreposto ao canto do cartão.
  &__leads {
    @include dark.values;
    @include color-roles;

    position: absolute;
    inset-block-end: var(--space-4);
    inset-inline-end: calc(var(--space-2) * -1);
    display: flex;
    gap: var(--space-2);
    align-items: center;
    padding: var(--space-3) var(--space-4);
    color: var(--color-text);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);

    @include up(lg) {
      inset-inline-end: calc(var(--space-16) * -1);
    }
  }

  &__leads-value {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
  }

  &__leads-text {
    display: grid;
    font-size: var(--font-size-xs);
    line-height: var(--line-height-tight);
  }

  &__leads-period {
    color: var(--color-text-muted);
  }

  &__leads-icon {
    color: var(--color-success);
  }
}
</style>
