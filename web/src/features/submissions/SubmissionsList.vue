<script setup lang="ts">
import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

import { queryKeys } from '@/api/queryKeys'
import { listSubmissions, type SubmissionFilters } from '@/api/submissions'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { submissions } from '@/strings'

import SubmissionListItem from './SubmissionListItem.vue'

/** Lista paginada por cursor ("Carregar mais"), com a resposta aberta marcada. */
const props = defineProps<{
  filters: SubmissionFilters
  selectedId?: string
  /** Há filtro além da ordem: muda o texto da lista vazia. */
  filtered: boolean
  /** Termo buscado, para a mensagem de lista vazia. */
  search?: string
}>()

const emit = defineEmits<{
  select: [id: string]
}>()

const t = submissions

const query = useInfiniteQuery({
  queryKey: computed(() => queryKeys.submissionList(props.filters)),
  queryFn: ({ pageParam }) => listSubmissions(props.filters, pageParam),
  initialPageParam: null as string | null,
  getNextPageParam: (page) => page.meta.next_cursor,
})

const items = computed(() => query.data.value?.pages.flatMap((page) => page.data) ?? [])
</script>

<template>
  <p v-if="query.isPending.value" class="submissions-list__status" role="status">
    <AppSpinner />
    {{ t.loading }}
  </p>

  <div v-else-if="query.isError.value" class="submissions-list__error">
    <AppAlert tone="danger">{{ t.loadError }}</AppAlert>
    <AppButton variant="secondary" icon-left="rotate-ccw" @click="query.refetch()">
      {{ t.retry }}
    </AppButton>
  </div>

  <div v-else-if="items.length === 0" class="submissions-list__empty">
    <AppIcon name="inbox" class="submissions-list__empty-icon" />
    <h2 class="submissions-list__empty-title">{{ t.empty.title }}</h2>
    <p class="submissions-list__empty-text">
      {{
        props.search
          ? t.empty.search(props.search)
          : props.filtered
            ? t.empty.filtered
            : t.empty.text
      }}
    </p>
  </div>

  <div v-else class="submissions-list">
    <ul class="submissions-list__items">
      <SubmissionListItem
        v-for="submission in items"
        :key="submission.id"
        :submission="submission"
        :selected="submission.id === props.selectedId"
        @select="emit('select', submission.id)"
      />
    </ul>
    <AppButton
      v-if="query.hasNextPage.value"
      variant="secondary"
      size="sm"
      class="submissions-list__more"
      :loading="query.isFetchingNextPage.value"
      @click="query.fetchNextPage()"
    >
      {{ t.loadMore }}
    </AppButton>
  </div>
</template>

<style scoped lang="scss">
.submissions-list {
  display: grid;
  gap: var(--space-3);
  padding-block-end: var(--space-3);

  &__items {
    display: grid;
    padding: 0;
    margin: 0;
    list-style: none;

    > * + * {
      border-block-start: var(--border-width) solid var(--color-border);
    }
  }

  &__more {
    justify-self: center;
  }

  &__status {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    justify-content: center;
    padding-block: var(--space-12);
    color: var(--color-text-muted);
  }

  &__error {
    display: grid;
    gap: var(--space-4);
    justify-items: start;
    padding: var(--space-4);
  }

  &__empty {
    display: grid;
    gap: var(--space-2);
    justify-items: center;
    padding: var(--space-12) var(--space-4);
    text-align: center;
  }

  &__empty-icon {
    font-size: var(--font-size-3xl);
    color: var(--color-text-subtle);
  }

  &__empty-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
  }

  &__empty-text {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
