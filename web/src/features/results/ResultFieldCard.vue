<script setup lang="ts">
import { computed } from 'vue'

import AppCard from '@/components/AppCard.vue'
import { results } from '@/strings'
import type { ResultField } from '@/types/api'

import ChoiceBars from './ChoiceBars.vue'
import DistributionBars from './DistributionBars.vue'
import { formatCount, formatDate, formatDecimal } from './format'

/** Um campo do formulário nos resultados: o gráfico ou números conforme o tipo. */
const props = defineProps<{
  field: ResultField
  total: number
}>()

const emit = defineEmits<{
  /** Abrir uma das últimas respostas de texto. */
  open: [submissionId: string]
}>()

const t = results

/** Últimas respostas dos campos de texto (o template não estreita a união sozinho). */
const texts = computed(() => {
  const field = props.field

  return field.type === 'short_text' || field.type === 'long_text' || field.type === 'email'
    ? field.latest
    : []
})
</script>

<template>
  <AppCard class="result-field">
    <header class="result-field__header">
      <h3 class="result-field__title">{{ props.field.label }}</h3>
      <p v-if="props.field.type !== 'file'" class="result-field__answered">
        {{ t.answered(props.field.answered, props.total) }}
      </p>
    </header>

    <p v-if="props.field.answered === 0" class="result-field__empty">{{ t.noAnswers }}</p>

    <ChoiceBars
      v-else-if="props.field.type === 'single_choice' || props.field.type === 'multiple_choice'"
      :options="props.field.options"
      :answered="props.field.answered"
    />

    <template v-else-if="props.field.type === 'rating' || props.field.type === 'scale'">
      <p class="result-field__headline">
        <span class="result-field__number" :data-count="props.field.average ?? 0" data-decimals>
          {{ formatDecimal(props.field.average) }}
        </span>
        <span class="result-field__unit">{{ t.average }}</span>
      </p>
      <DistributionBars :distribution="props.field.distribution" :answered="props.field.answered" />
    </template>

    <dl v-else-if="props.field.type === 'number'" class="result-field__stats">
      <div class="result-field__stat">
        <dt class="result-field__stat-label">{{ t.min }}</dt>
        <dd class="result-field__stat-value">{{ formatDecimal(props.field.min) }}</dd>
      </div>
      <div class="result-field__stat">
        <dt class="result-field__stat-label">{{ t.average }}</dt>
        <dd class="result-field__stat-value">{{ formatDecimal(props.field.average) }}</dd>
      </div>
      <div class="result-field__stat">
        <dt class="result-field__stat-label">{{ t.max }}</dt>
        <dd class="result-field__stat-value">{{ formatDecimal(props.field.max) }}</dd>
      </div>
    </dl>

    <dl v-else-if="props.field.type === 'date'" class="result-field__stats">
      <div class="result-field__stat">
        <dt class="result-field__stat-label">{{ t.earliest }}</dt>
        <dd class="result-field__stat-value">{{ formatDate(props.field.earliest) }}</dd>
      </div>
      <div class="result-field__stat">
        <dt class="result-field__stat-label">{{ t.latestDate }}</dt>
        <dd class="result-field__stat-value">{{ formatDate(props.field.latest) }}</dd>
      </div>
    </dl>

    <p v-else-if="props.field.type === 'file'" class="result-field__headline">
      <span class="result-field__number" :data-count="props.field.answered">
        {{ formatCount(props.field.answered) }}
      </span>
      <span class="result-field__unit">{{ t.files(props.field.answered) }}</span>
    </p>

    <div v-else class="result-field__texts">
      <p class="result-field__subtitle">{{ t.latestTexts }}</p>
      <ul class="result-field__latest">
        <li v-for="item in texts" :key="item.submission_id">
          <button
            type="button"
            class="result-field__text"
            :aria-label="t.openSubmission(item.value)"
            @click="emit('open', item.submission_id)"
          >
            {{ item.value }}
          </button>
        </li>
      </ul>
    </div>
  </AppCard>
</template>

<style scoped lang="scss">
.result-field {
  display: grid;
  gap: var(--space-4);
  align-content: start;

  &__header {
    display: grid;
    gap: var(--space-1);
  }

  &__title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
  }

  &__answered,
  &__empty,
  &__subtitle {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__headline {
    display: flex;
    gap: var(--space-2);
    align-items: baseline;
  }

  &__number {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    font-variant-numeric: tabular-nums;
    line-height: 1;
  }

  &__unit {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__stats {
    display: grid;
    grid-auto-columns: minmax(0, 1fr);
    grid-auto-flow: column;
    gap: var(--space-3);
  }

  &__stat {
    display: grid;
    gap: var(--space-1);
  }

  &__stat-label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__stat-value {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    font-variant-numeric: tabular-nums;
  }

  &__texts {
    display: grid;
    gap: var(--space-2);
  }

  &__latest {
    display: grid;
    gap: var(--space-1);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__text {
    display: block;
    inline-size: 100%;
    padding: var(--space-2) var(--space-3);
    overflow: hidden;
    font: inherit;
    font-size: var(--font-size-sm);
    color: var(--color-text);
    text-align: start;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    background: var(--color-surface-raised);
    border: 0;
    border-radius: var(--radius-sm);
    transition: background-color var(--duration-fast) var(--ease-out);

    &:hover {
      background: var(--color-surface-hover);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }
}
</style>
