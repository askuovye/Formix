<script setup lang="ts">
import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import { listForms } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppConfirmDialog from '@/components/AppConfirmDialog.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { forms } from '@/strings'
import type { FormStatus, FormSummary } from '@/types/api'

import FormListItem, { type FormAction } from './FormListItem.vue'
import { useFormActions } from './useFormActions'

const props = defineProps<{
  /** Situação filtrada; `all` = todas. */
  status: FormStatus | 'all'
}>()

const t = forms
const router = useRouter()

const query = useInfiniteQuery({
  queryKey: computed(() => queryKeys.formList(props.status)),
  queryFn: ({ pageParam }) =>
    listForms({ status: props.status === 'all' ? undefined : props.status, cursor: pageParam }),
  initialPageParam: null as string | null,
  getNextPageParam: (page) => page.meta.next_cursor,
})

const items = computed(() => query.data.value?.pages.flatMap((page) => page.data) ?? [])

const { archive, reopen, remove, copyLink } = useFormActions()

/** Ação esperando confirmação na janela. */
const pending = ref<{ kind: 'archive' | 'reopen' | 'delete'; form: FormSummary } | null>(null)

const confirmOpen = computed({
  get: () => pending.value !== null,
  set: (open) => {
    if (!open) pending.value = null
  },
})

const confirmText = computed(() => {
  if (!pending.value) return null
  const { kind, form } = pending.value

  return kind === 'delete'
    ? { ...t.confirm.delete, text: t.confirm.delete.text(form.title) }
    : t.confirm[kind]
})

const mutations = { archive, reopen, delete: remove }

const confirming = computed(() =>
  pending.value ? mutations[pending.value.kind].isPending.value : false,
)

function onAction(form: FormSummary, action: FormAction): void {
  switch (action) {
    case 'edit':
      void router.push({ name: 'form-builder', params: { id: form.id } })
      break
    case 'submissions':
      void router.push({ name: 'submissions', query: { form: form.id } })
      break
    case 'copy-link':
      void copyLink(form.public_url)
      break
    case 'reopen':
      // Reabrir publica o rascunho: só pede confirmação se houver mudança a publicar.
      if (form.has_unpublished_changes) {
        pending.value = { kind: 'reopen', form }
      } else {
        reopen.mutate(form.id)
      }
      break
    default:
      pending.value = { kind: action, form }
  }
}

function confirm(): void {
  if (!pending.value) return

  mutations[pending.value.kind].mutate(pending.value.form.id, {
    onSettled: () => (pending.value = null),
  })
}
</script>

<template>
  <p v-if="query.isPending.value" class="forms-list__status" role="status">
    <AppSpinner />
    {{ t.loading }}
  </p>

  <div v-else-if="query.isError.value" class="forms-list__error">
    <AppAlert tone="danger">{{ t.loadError }}</AppAlert>
    <AppButton variant="secondary" icon-left="rotate-ccw" @click="query.refetch()">
      {{ t.retry }}
    </AppButton>
  </div>

  <AppCard v-else-if="items.length === 0" padding="lg" class="forms-list__empty">
    <AppIcon name="file-text" class="forms-list__empty-icon" />
    <h2 class="forms-list__empty-title">{{ t.empty[props.status].title }}</h2>
    <p class="forms-list__empty-text">{{ t.empty[props.status].text }}</p>
    <slot name="empty-action" />
  </AppCard>

  <template v-else>
    <AppCard padding="none" class="forms-list">
      <ul class="forms-list__items">
        <FormListItem
          v-for="form in items"
          :key="form.id"
          :form="form"
          @action="(action) => onAction(form, action)"
        />
      </ul>
    </AppCard>
    <AppButton
      v-if="query.hasNextPage.value"
      variant="secondary"
      class="forms-list__more"
      :loading="query.isFetchingNextPage.value"
      @click="query.fetchNextPage()"
    >
      {{ t.loadMore }}
    </AppButton>
  </template>

  <AppConfirmDialog
    v-if="confirmText"
    v-model:open="confirmOpen"
    :title="confirmText.title"
    :description="confirmText.text"
    :confirm-label="confirmText.confirm"
    :cancel-label="t.confirm.cancel"
    :tone="pending?.kind === 'delete' ? 'danger' : 'primary'"
    :loading="confirming"
    @confirm="confirm"
  />
</template>

<style scoped lang="scss">
.forms-list {
  overflow: hidden;

  &__items {
    display: grid;
    padding: 0;
    margin: 0;
    list-style: none;

    > * + * {
      border-block-start: var(--border-width) solid var(--color-border);
    }
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
  }

  &__empty {
    display: grid;
    gap: var(--space-2);
    justify-items: center;
    padding-block: var(--space-12);
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
    max-width: var(--dialog-width-md);
    margin-block-end: var(--space-2);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__more {
    justify-self: center;
    margin-block-start: var(--space-4);
  }
}
</style>
