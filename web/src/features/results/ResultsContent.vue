<script setup lang="ts">
import { gsap } from 'gsap'
import { ref } from 'vue'

import AppCard from '@/components/AppCard.vue'
import { useGsapContext } from '@/composables/useGsapContext'
import { chart } from '@/motion'
import { gsapEase } from '@/motion/gsap'
import { results } from '@/strings'
import type { FormResults } from '@/types/api'

import { formatCount, formatDecimal } from './format'
import ResultFieldCard from './ResultFieldCard.vue'
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
    <div class="results__overview">
      <dl class="results__stats">
        <div class="results__stat">
          <dt class="results__stat-label">{{ t.stats.total }}</dt>
          <dd class="results__stat-value" :data-count="props.data.total">
            {{ formatCount(props.data.total) }}
          </dd>
        </div>
        <div class="results__stat">
          <dt class="results__stat-label">{{ t.stats.lastWeek }}</dt>
          <dd class="results__stat-value" :data-count="props.data.last_7_days">
            {{ formatCount(props.data.last_7_days) }}
          </dd>
        </div>
        <div class="results__stat">
          <dt class="results__stat-label">{{ t.stats.edited }}</dt>
          <dd class="results__stat-value" :data-count="props.data.edited">
            {{ formatCount(props.data.edited) }}
          </dd>
        </div>
      </dl>

      <AppCard class="results__timeline">
        <ResultsTimeline :timeline="props.data.timeline" />
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

  &__overview {
    display: grid;
    gap: var(--space-4);

    @include up(lg) {
      grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    }
  }

  &__stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-3);

    @include up(lg) {
      grid-template-columns: minmax(0, 1fr);
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

  &__stat-value {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-bold);
    font-variant-numeric: tabular-nums;
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
