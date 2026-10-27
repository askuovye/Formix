<script setup lang="ts">
import { gsap } from 'gsap'
import { computed, ref } from 'vue'

import AppCard from '@/components/AppCard.vue'
import AppIcon from '@/components/AppIcon.vue'
import { useGsapContext } from '@/composables/useGsapContext'
import { chart } from '@/motion'
import { gsapEase } from '@/motion/gsap'
import { results } from '@/strings'
import type { FormResults } from '@/types/api'

import { changeOf, formatCount, formatDecimal, formatDuration } from './format'
import ResultFieldCard from './ResultFieldCard.vue'
import ResultsDevices from './ResultsDevices.vue'
import ResultsTimeline from './ResultsTimeline.vue'

/**
 * Resultados já carregados. A entrada anima uma vez (barras crescem da base e os
 * números sobem até o valor); as rebuscas seguintes só trocam os valores pelo Vue.
 * Só transform e o texto do número; com movimento reduzido, nada roda.
 */
const props = defineProps<{
  data: FormResults
}>()

const emit = defineEmits<{
  open: [submissionId: string]
}>()

const t = results
const root = ref<HTMLElement | null>(null)

interface Stat {
  key: string
  label: string
  text: string
  /** Contagem que sobe na entrada; o tempo médio só aparece. */
  count?: number
  /** Variação contra o período anterior (%), quando há com o que comparar. */
  change: number | null
}

/**
 * Indicadores no topo. A variação é neutra (sem verde/vermelho): mais tempo ou
 * menos respostas não são, por si, bons ou ruins.
 */
const stats = computed<Stat[]>(() => {
  const { data } = props
  const previous = data.previous

  return [
    {
      key: 'total',
      label: t.stats.total,
      text: formatCount(data.total),
      count: data.total,
      change: changeOf(data.total, previous?.total),
    },
    {
      key: 'duration',
      label: t.stats.duration,
      text: formatDuration(data.average_duration_seconds),
      change: changeOf(data.average_duration_seconds, previous?.average_duration_seconds),
    },
    {
      key: 'lastWeek',
      label: t.stats.lastWeek,
      text: formatCount(data.last_7_days),
      count: data.last_7_days,
      change: null,
    },
    {
      key: 'edited',
      label: t.stats.edited,
      text: formatCount(data.edited),
      count: data.edited,
      change: null,
    },
  ]
})

useGsapContext(root, () => {
  const tween = { duration: chart.grow, ease: gsapEase.out, stagger: chart.stagger }

  gsap.from('.js-grow-y', { ...tween, scaleY: 0 })
  gsap.from('.js-grow-x', { ...tween, scaleX: 0 })

  for (const element of gsap.utils.toArray<HTMLElement>('[data-count]')) {
    const target = Number(element.dataset.count)
    const decimals = element.hasAttribute('data-decimals')
    // Mexe no nó de texto que o Vue controla (não troca o nó), para as
    // atualizações seguintes continuarem aparecendo.
    const text = element.firstChild
    if (!(text instanceof Text)) continue
    const counter = { value: 0 }

    gsap.to(counter, {
      value: target,
      duration: chart.grow,
      ease: gsapEase.out,
      onUpdate: () => {
        text.nodeValue = decimals
          ? formatDecimal(counter.value)
          : formatCount(Math.round(counter.value))
      },
    })
  }
})
</script>

<template>
  <div ref="root" class="results">
    <dl class="results__stats">
      <div v-for="stat in stats" :key="stat.key" class="results__stat">
        <dt class="results__stat-label">{{ stat.label }}</dt>
        <dd class="results__stat-body">
          <span class="results__stat-value" :data-count="stat.count">{{ stat.text }}</span>
          <span v-if="stat.change !== null" class="results__stat-change">
            <AppIcon
              v-if="stat.change !== 0"
              :name="stat.change > 0 ? 'trending-up' : 'trending-down'"
            />
            <span aria-hidden="true">{{ t.change.short(stat.change) }}</span>
            <span class="results__sr-only">{{ t.change.label(stat.change) }}</span>
          </span>
        </dd>
      </div>
    </dl>

    <div class="results__charts">
      <AppCard>
        <ResultsTimeline :timeline="props.data.timeline" />
      </AppCard>
      <AppCard>
        <ResultsDevices :devices="props.data.devices" />
      </AppCard>
    </div>

    <div class="results__fields">
      <ResultFieldCard
        v-for="field in props.data.fields"
        :key="field.id"
        :field="field"
        :total="props.data.total"
        @open="(id) => emit('open', id)"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.results {
  display: grid;
  gap: var(--space-4);

  &__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-3);

    @include up(md) {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }

  &__charts {
    display: grid;
    gap: var(--space-4);

    @include up(lg) {
      grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    }
  }

  &__stat {
    display: grid;
    gap: var(--space-1);
    align-content: center;
    padding: var(--space-4);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
  }

  &__stat-label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__stat-body {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-1) var(--space-3);
    align-items: baseline;
    justify-content: space-between;
  }

  &__stat-value {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-bold);
    font-variant-numeric: tabular-nums;
  }

  &__stat-change {
    display: inline-flex;
    gap: var(--space-1);
    align-items: center;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    font-variant-numeric: tabular-nums;
    color: var(--color-text-muted);
  }

  &__sr-only {
    @include visually-hidden;
  }

  &__fields {
    display: grid;
    gap: var(--space-4);
    align-items: start;

    @include up(md) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
}
</style>
