<script setup lang="ts">
import { computed } from 'vue'

import { results } from '@/strings'

import { formatCount, percentOf } from './format'

/**
 * Estrelas e escala: uma coluna por valor possível (inclusive os com zero),
 * contagem em cima e o valor embaixo. Sempre do menor para o maior.
 */
const props = defineProps<{
  distribution: { value: number; count: number }[]
  answered: number
}>()

const peak = computed(() => Math.max(0, ...props.distribution.map((item) => item.count)))

const columns = computed(() =>
  props.distribution.map((item) => {
    const percent = percentOf(item.count, props.answered)

    return {
      ...item,
      height: peak.value > 0 ? (item.count / peak.value) * 100 : 0,
      text: results.option(String(item.value), item.count, `${percent}%`),
    }
  }),
)
</script>

<template>
  <ol class="distribution">
    <li
      v-for="column in columns"
      :key="column.value"
      class="distribution__column"
      :aria-label="column.text"
      :title="column.text"
    >
      <span class="distribution__count" aria-hidden="true">{{ formatCount(column.count) }}</span>
      <span class="distribution__slot" aria-hidden="true">
        <span
          class="distribution__bar js-grow-y"
          :class="{ 'distribution__bar--empty': column.count === 0 }"
          :style="{ blockSize: `${column.height}%` }"
        />
      </span>
      <span class="distribution__value" aria-hidden="true">{{ column.value }}</span>
    </li>
  </ol>
</template>

<style scoped lang="scss">
.distribution {
  display: grid;
  grid-auto-columns: minmax(0, 1fr);
  grid-auto-flow: column;
  gap: var(--space-1);
    padding: 0;
    margin: 0;
    list-style: none;

  &__column {
    display: grid;
    grid-template-rows: auto var(--chart-distribution-height) auto;
    gap: var(--space-1);
    justify-items: center;
    font-variant-numeric: tabular-nums;
  }

  &__count {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__slot {
    display: flex;
    align-items: flex-end;
    inline-size: 100%;
    border-block-end: var(--border-width) solid var(--color-border);
  }

  &__bar {
    inline-size: 100%;
    min-block-size: var(--chart-bar-min);
    background: var(--color-chart-bar);
    border-start-start-radius: var(--radius-xs);
    border-start-end-radius: var(--radius-xs);
    transform-origin: bottom;

    &--empty {
      background: var(--color-chart-track);
    }
  }

  &__value {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }
}
</style>
