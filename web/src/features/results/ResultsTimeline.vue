<script setup lang="ts">
import { computed } from 'vue'

import { results } from '@/strings'
import type { FormResults } from '@/types/api'

import { formatShortDate } from './format'

/**
 * Respostas por dia (ou semana): barras verticais numa série só. Cada barra
 * tem o valor no tooltip nativo e a tabela escondida dá o mesmo para leitores
 * de tela. Rótulos do eixo só no início, meio e fim (evita amontoar).
 */
const props = defineProps<{
  timeline: FormResults['timeline']
}>()

const t = results.timeline

const peak = computed(() => Math.max(0, ...props.timeline.points.map((point) => point.count)))

const bars = computed(() =>
  props.timeline.points.map((point, index, all) => {
    const label =
      props.timeline.unit === 'week'
        ? t.week(formatShortDate(point.date))
        : formatShortDate(point.date)

    return {
      ...point,
      label,
      height: peak.value > 0 ? (point.count / peak.value) * 100 : 0,
      tick: index === 0 || index === all.length - 1 || index === Math.floor(all.length / 2),
    }
  }),
)
</script>

<template>
  <figure class="timeline">
    <figcaption class="timeline__header">
      <span class="timeline__title">{{ t.title[props.timeline.unit] }}</span>
      <span v-if="peak > 0" class="timeline__peak">{{ t.peak(peak) }}</span>
    </figcaption>

    <ol class="timeline__plot" aria-hidden="true">
      <li
        v-for="bar in bars"
        :key="bar.date"
        class="timeline__slot"
        :title="t.bar(bar.label, bar.count)"
      >
        <span
          class="timeline__bar js-grow-y"
          :class="{ 'timeline__bar--empty': bar.count === 0 }"
          :style="{ blockSize: `${bar.height}%` }"
        />
      </li>
    </ol>

    <div class="timeline__axis" aria-hidden="true">
      <span v-for="bar in bars" :key="bar.date" class="timeline__tick">
        {{ bar.tick ? formatShortDate(bar.date) : '' }}
      </span>
    </div>

    <table class="timeline__table">
      <caption>
        {{
          results.table
        }}
      </caption>
      <tbody>
        <tr v-for="bar in bars" :key="bar.date">
          <th scope="row">{{ bar.label }}</th>
          <td>{{ bar.count }}</td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>

<style scoped lang="scss">
.timeline {
  display: grid;
  gap: var(--space-3);

  &__header {
    display: flex;
    gap: var(--space-3);
    align-items: baseline;
    justify-content: space-between;
  }

  &__title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__peak {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__plot,
  &__axis {
    display: grid;
    grid-auto-columns: minmax(0, 1fr);
    grid-auto-flow: column;
    gap: var(--chart-bar-gap);
  }

  &__plot {
    align-items: end;
    padding: 0;
    margin: 0;
    list-style: none;
    block-size: var(--chart-timeline-height);
    border-block-end: var(--border-width) solid var(--color-border);
  }

  // Área de toque maior que a barra: o tooltip aparece em qualquer ponto da coluna.
  &__slot {
    display: flex;
    align-items: flex-end;
    block-size: 100%;
  }

  &__bar {
    inline-size: 100%;
    min-block-size: var(--chart-bar-min);
    background: var(--color-chart-bar);
    border-start-start-radius: var(--radius-xs);
    border-start-end-radius: var(--radius-xs);
    transform-origin: bottom;
    transition: background-color var(--duration-fast) var(--ease-out);

    &--empty {
      background: var(--color-chart-track);
    }
  }

  &__slot:hover &__bar {
    background: var(--color-chart-bar-muted);
  }

  &__tick {
    overflow: visible;
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
    white-space: nowrap;
  }

  // O último rótulo encosta na direita para não vazar do card.
  &__tick:last-child {
    direction: rtl;
  }

  &__table {
    @include visually-hidden;
  }
}
</style>
