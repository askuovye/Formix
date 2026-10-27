<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import type { IconName } from '@/components/icons/iconNames'
import { BRAND_NAME } from '@/config/brand'
import { landing } from '@/strings'

/**
 * "Tudo o que você precisa em um só lugar": faixa com o tema oposto ao do app
 * (clara no escuro, escura no claro), lista de passos e um cartão de respostas
 * com gráfico de linha ilustrativo. Alvo de "Ver como funciona".
 */
const t = landing.showcase

const steps: { icon: IconName; label: string }[] = [
  { icon: 'palette', label: t.checklist[0] },
  { icon: 'plus', label: t.checklist[1] },
  { icon: 'git-branch', label: t.checklist[2] },
  { icon: 'send', label: t.checklist[3] },
]

/** Pontos do gráfico (0 a 100), subindo com oscilação, como na referência. */
const points = [12, 20, 16, 30, 26, 42, 38, 55, 50, 68, 62, 80, 88]
const path = points
  .map((value, index) => {
    const x = (index / (points.length - 1)) * 100
    const y = 100 - value

    return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y}`
  })
  .join(' ')
const area = `${path} L100,100 L0,100 Z`

const formatter = new Intl.NumberFormat('pt-BR')
</script>

<template>
  <section id="como-funciona" class="showcase" aria-labelledby="showcase-title">
    <div class="showcase__inner">
      <div class="showcase__copy">
        <h2 id="showcase-title" class="showcase__title">
          <template v-for="line in t.title" :key="line">
            <span class="showcase__line">{{ line }}</span>
            {{ ' ' }}
          </template>
        </h2>
        <p class="showcase__text">{{ t.text(BRAND_NAME) }}</p>
        <RouterLink :to="{ hash: '#recursos' }" class="showcase__more">
          {{ t.more }}
          <AppIcon name="arrow-right" />
        </RouterLink>
      </div>

      <div class="showcase__cards">
        <div class="showcase__checklist">
          <p class="showcase__card-title">{{ t.checklistTitle }}</p>
          <ul class="showcase__steps">
            <li v-for="step in steps" :key="step.label" class="showcase__step js-step">
              <AppIcon :name="step.icon" class="showcase__step-icon" />
              {{ step.label }}
            </li>
          </ul>
        </div>

        <figure class="showcase__stats">
          <figcaption class="showcase__stats-header">
            <span class="showcase__stats-label">{{ t.statsLabel }}</span>
            <span class="showcase__stats-trend">{{ t.statsTrend }}</span>
          </figcaption>
          <p class="showcase__stats-value" :data-count="t.statsValue">
            {{ formatter.format(t.statsValue) }}
          </p>
          <svg
            class="showcase__chart"
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            role="img"
            :aria-label="t.chart"
          >
            <path :d="area" class="showcase__chart-area js-chart-area" />
            <path
              :d="path"
              class="showcase__chart-line js-chart-line"
              pathLength="1"
              vector-effect="non-scaling-stroke"
            />
          </svg>
        </figure>
      </div>
    </div>
  </section>
</template>

<style scoped lang="scss">
@use '@/styles/themes/dark';
@use '@/styles/themes/light';

// Tema oposto ao do app: claro por padrão (o app nasce escuro), escuro no tema claro.
.showcase {
  @include light.values;
  @include color-roles;

  color: var(--color-text);
  background: var(--color-bg);
  border-radius: var(--radius-xl);
  scroll-margin-block-start: var(--space-8);

  :root[data-theme='light'] & {
    @include dark.values;
    @include color-roles;
  }

  @media (prefers-color-scheme: light) {
    :root:not([data-theme]) & {
      @include dark.values;
      @include color-roles;
    }
  }

  &__inner {
    display: grid;
    gap: var(--space-10);
    align-items: center;
    padding: var(--space-10) var(--space-6);

    @include up(md) {
      padding: var(--space-12);
    }

    @include up(lg) {
      grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
      padding: var(--space-16);
    }
  }

  &__copy {
    display: grid;
    gap: var(--space-4);
    justify-items: start;
  }

  &__title {
    display: grid;
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
  }

  &__text {
    max-inline-size: var(--layout-width-text);
    line-height: var(--line-height-base);
    color: var(--color-text-muted);
  }

  &__more {
    display: inline-flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text);
    text-decoration: none;
    border-radius: var(--radius-xs);

    &:hover {
      text-decoration: underline;
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__cards {
    display: grid;
    gap: var(--space-4);
    align-items: center;

    @include up(sm) {
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }
  }

  &__checklist {
    display: grid;
    gap: var(--space-4);
    padding: var(--space-6);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
  }

  &__card-title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__steps {
    display: grid;
    gap: var(--space-3);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__step {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__step-icon {
    color: var(--color-text);
  }

  // Cartão de respostas: sempre escuro (como o painel do produto), nos dois temas.
  &__stats {
    @include dark.values;
    @include color-roles;

    display: grid;
    gap: var(--space-2);
    padding: var(--space-6);
    margin: 0;
    color: var(--color-text);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
  }

  &__stats-header {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-xs);
  }

  &__stats-label {
    color: var(--color-text-muted);
  }

  &__stats-trend {
    font-weight: var(--font-weight-semibold);
    color: var(--color-success);
  }

  &__stats-value {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    font-variant-numeric: tabular-nums;
    line-height: var(--line-height-none);
  }

  &__chart {
    inline-size: 100%;
    block-size: var(--landing-chart-height);
    margin-block-start: var(--space-2);
    overflow: visible;
  }

  &__chart-area {
    fill: var(--color-success-soft);
  }

  &__chart-line {
    fill: none;
    stroke: var(--color-success);
    stroke-width: var(--border-width-strong);
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}
</style>
