<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

import { queryKeys } from '@/api/queryKeys'
import { fetchResults, type ResultsFilters } from '@/api/results'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { results } from '@/strings'

import ResultsContent from './ResultsContent.vue'

/** Aba "Resultados": busca os agregados com os filtros da tela e mostra os gráficos. */
const props = defineProps<{
  formId: string
  filters: ResultsFilters
}>()

const emit = defineEmits<{
  open: [submissionId: string]
}>()

const t = results

const query = useQuery({
  queryKey: computed(() => queryKeys.formResults(props.formId, props.filters)),
  queryFn: () => fetchResults(props.formId, props.filters),
})
</script>

<template>
  <div class="results-panel" :aria-busy="query.isFetching.value">
    <p v-if="query.isPending.value" class="results-panel__state">
      <AppSpinner />
      {{ t.loading }}
    </p>

    <AppAlert v-else-if="query.isError.value" tone="danger">
      {{ t.loadError }}
      <AppButton size="sm" variant="secondary" @click="query.refetch()">{{ t.retry }}</AppButton>
    </AppAlert>

    <div v-else-if="query.data.value?.total === 0" class="results-panel__state">
      <AppIcon name="chart-column" class="results-panel__icon" />
      <h2 class="results-panel__title">{{ t.empty.title }}</h2>
      <p>{{ t.empty.text }}</p>
    </div>

    <ResultsContent
      v-else-if="query.data.value"
      :key="query.dataUpdatedAt.value"
      :data="query.data.value"
      @open="(id) => emit('open', id)"
    />
  </div>
</template>

<style scoped lang="scss">
.results-panel {
  &__state {
    display: grid;
    gap: var(--space-3);
    justify-items: center;
    padding: var(--space-16) var(--space-6);
    color: var(--color-text-muted);
    text-align: center;
  }

  &__icon {
    font-size: var(--font-size-3xl);
    color: var(--color-text-subtle);
  }

  &__title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text);
  }
}
</style>
