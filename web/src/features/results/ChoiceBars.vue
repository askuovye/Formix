<script setup lang="ts">
import { computed } from 'vue'

import { results } from '@/strings'

import { formatCount, percentOf } from './format'

/**
 * Opções de escolha em barras horizontais (magnitude numa cor só), na ordem do
 * formulário. Contagem e % ficam escritos ao lado: o valor nunca depende só da cor.
 * Em múltipla escolha o % é sobre quem respondeu (a soma pode passar de 100%).
 */
const props = defineProps<{
  options: { value: string; label: string; count: number }[]
  answered: number
}>()

const rows = computed(() =>
  props.options.map((option) => {
    const percent = percentOf(option.count, props.answered)

    return {
      ...option,
      percent,
      text: results.option(option.label, option.count, `${percent}%`),
    }
  }),
)
</script>

<template>
  <ul class="choice-bars">
    <li v-for="row in rows" :key="row.value" class="choice-bars__row" :aria-label="row.text">
      <div class="choice-bars__legend" aria-hidden="true">
        <span class="choice-bars__label">{{ row.label }}</span>
        <span class="choice-bars__value">
          {{ formatCount(row.count) }}
          <span class="choice-bars__percent">{{ row.percent }}%</span>
        </span>
      </div>
      <div class="choice-bars__track" aria-hidden="true" :title="row.text">
        <span
          v-if="row.count > 0"
          class="choice-bars__fill js-grow-x"
          :style="{ inlineSize: `${row.percent}%` }"
        />
      </div>
    </li>
  </ul>
</template>

<style scoped lang="scss">
.choice-bars {
  display: grid;
  gap: var(--space-3);
    padding: 0;
    margin: 0;
    list-style: none;

  &__row {
    display: grid;
    gap: var(--space-1);
  }

  &__legend {
    display: flex;
    gap: var(--space-3);
    align-items: baseline;
    justify-content: space-between;
    font-size: var(--font-size-sm);
  }

  &__label {
    min-inline-size: 0;
    overflow-wrap: anywhere;
  }

  &__value {
    flex: none;
    font-weight: var(--font-weight-semibold);
    font-variant-numeric: tabular-nums;
  }

  &__percent {
    margin-inline-start: var(--space-1);
    font-weight: var(--font-weight-regular);
    color: var(--color-text-muted);
  }

  &__track {
    display: flex;
    block-size: var(--chart-bar-thickness);
    overflow: hidden;
    background: var(--color-chart-track);
    border-radius: var(--radius-xs);
  }

  &__fill {
    min-inline-size: var(--chart-bar-min);
    background: var(--color-chart-bar);
    border-radius: var(--radius-xs);
    transform-origin: left;
  }
}
</style>
