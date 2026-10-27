<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'

import { listForms } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppSelect from '@/components/AppSelect.vue'
import AppTextField from '@/components/AppTextField.vue'
import { useUrlSearch } from '@/composables/useUrlSearch'
import ExportDialog from '@/features/submissions/ExportDialog.vue'
import SubmissionDetail from '@/features/submissions/SubmissionDetail.vue'
import SubmissionsList from '@/features/submissions/SubmissionsList.vue'
import {
  PERIODS,
  SORTS,
  isPeriod,
  isSort,
  toFilters,
  type Period,
  type Sort,
} from '@/features/submissions/filters'
import { submissions } from '@/strings'

const t = submissions
const route = useRoute()
const router = useRouter()

/** Filtros e resposta aberta ficam na URL: sobrevivem ao recarregar e dá para compartilhar. */
const ALL = 'all'
const formId = computed(() =>
  typeof route.query.form === 'string' && route.query.form !== '' ? route.query.form : undefined,
)
const period = computed<Period>(() => (isPeriod(route.query.periodo) ? route.query.periodo : 'all'))
const sort = computed<Sort>(() => (isSort(route.query.ordem) ? route.query.ordem : 'newest'))
const selectedId = computed(() =>
  typeof route.query.resposta === 'string' ? route.query.resposta : undefined,
)

const { input: searchInput, term: search } = useUrlSearch()

const filters = computed(() => toFilters(formId.value, period.value, sort.value, search.value))

function setQuery(changes: LocationQueryRaw): void {
  void router.replace({ query: { ...route.query, ...changes } })
}

/** No celular o detalhe toma o lugar da lista: volta ao topo para ler do começo. */
function open(id: string): void {
  setQuery({ resposta: id })
  if (!window.matchMedia('(min-width: 1024px)').matches) window.scrollTo({ top: 0 })
}

/** Mudar filtro fecha a resposta aberta (ela pode nem estar mais na lista). */
function setFilter(changes: LocationQueryRaw): void {
  setQuery({ ...changes, resposta: undefined })
}

const formsQuery = useQuery({
  queryKey: queryKeys.formOptions,
  queryFn: () => listForms({ perPage: 50 }),
  staleTime: 60_000,
})

const formOptions = computed(() => [
  { value: ALL, label: t.filters.allForms },
  ...(formsQuery.data.value?.data ?? []).map((form) => ({ value: form.id, label: form.title })),
])
const formChoices = computed(() => formOptions.value.filter((option) => option.value !== ALL))
const exporting = ref(false)

const periodOptions = PERIODS.map((value) => ({ value, label: t.filters.periods[value] }))
const sortOptions = SORTS.map((value) => ({ value, label: t.filters.sorts[value] }))
</script>

<template>
  <AppPageHeader :title="t.title" :subtitle="t.subtitle">
    <template #actions>
      <AppButton variant="secondary" icon-left="download" @click="exporting = true">
        {{ t.export.open }}
      </AppButton>
    </template>
  </AppPageHeader>

  <div
    class="submissions-page__filters"
    :class="{ 'submissions-page__filters--behind': selectedId }"
    role="group"
    :aria-label="t.filters.label"
  >
    <AppTextField
      v-model="searchInput"
      type="search"
      :label="t.filters.search"
      :placeholder="t.filters.searchPlaceholder"
      icon-left="search"
      :maxlength="100"
      class="submissions-page__search"
    />
    <AppSelect
      :model-value="formId ?? ALL"
      :label="t.filters.form"
      :options="formOptions"
      @update:model-value="(value) => setFilter({ form: value === ALL ? undefined : value })"
    />
    <AppSelect
      :model-value="period"
      :label="t.filters.period"
      :options="periodOptions"
      @update:model-value="(value) => setFilter({ periodo: value === 'all' ? undefined : value })"
    />
    <AppSelect
      :model-value="sort"
      :label="t.filters.sort"
      :options="sortOptions"
      @update:model-value="(value) => setFilter({ ordem: value === 'newest' ? undefined : value })"
    />
  </div>

  <div class="submissions-page" :class="{ 'submissions-page--detail': selectedId }">
    <AppCard padding="none" class="submissions-page__list">
      <SubmissionsList
        :filters="filters"
        :selected-id="selectedId"
        :filtered="formId !== undefined || period !== 'all'"
        :search="search"
        @select="open"
      />
    </AppCard>

    <AppCard padding="none" class="submissions-page__detail">
      <SubmissionDetail
        v-if="selectedId"
        :id="selectedId"
        :key="selectedId"
        @back="setQuery({ resposta: undefined })"
        @deleted="setQuery({ resposta: undefined })"
      />
      <p v-else class="submissions-page__placeholder">
        <AppIcon name="inbox" class="submissions-page__placeholder-icon" />
        {{ t.detail.select }}
      </p>
    </AppCard>
  </div>

  <ExportDialog
    v-model:open="exporting"
    :forms="formChoices"
    :initial-form-id="formId"
    :from="filters.from"
    :period-label="t.filters.periods[period]"
    :search="search || undefined"
  />
</template>

<style scoped lang="scss">
.submissions-page {
  display: grid;
  gap: var(--space-4);
  align-items: start;

  &__filters {
    display: grid;
    gap: var(--space-3);
    margin-block-end: var(--space-4);

    @include up(sm) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    // Celular com uma resposta aberta: os filtros saem da frente.
    &--behind {
      display: none;

      @include up(lg) {
        display: grid;
      }
    }
  }

  // Busca numa linha só, acima dos três filtros.
  &__search {
    grid-column: 1 / -1;
  }

  &__list {
    overflow: hidden;
  }

  // Celular: lista ou detalhe, um de cada vez.
  &__detail {
    display: none;
  }

  &--detail &__list {
    display: none;
  }

  &--detail &__detail {
    display: block;
  }

  &__placeholder {
    display: grid;
    gap: var(--space-3);
    justify-items: center;
    padding: var(--space-16) var(--space-6);
    color: var(--color-text-muted);
    text-align: center;
  }

  &__placeholder-icon {
    font-size: var(--font-size-3xl);
    color: var(--color-text-subtle);
  }

  // Desktop: lista à esquerda, detalhe ao lado acompanhando a rolagem.
  @include up(lg) {
    grid-template-columns: var(--submissions-list-width) minmax(0, 1fr);

    &__list,
    &--detail &__list {
      display: block;
    }

    &__detail {
      position: sticky;
      inset-block-start: var(--space-8);
      display: block;
      max-block-size: calc(100dvh - var(--space-16));
      overflow-y: auto;
    }
  }
}
</style>
