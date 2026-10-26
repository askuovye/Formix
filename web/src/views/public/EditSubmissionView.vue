<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import { toApiError } from '@/api/client'
import { fetchEditableSubmission } from '@/api/publicForms'
import { queryKeys } from '@/api/queryKeys'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { BRAND_NAME } from '@/config/brand'
import FormPage from '@/features/renderer/FormPage.vue'
import FormStatusPanel from '@/features/renderer/FormStatusPanel.vue'
import PublicFormRenderer from '@/features/renderer/PublicFormRenderer.vue'
import { renderer } from '@/strings'
import type { EditableSubmission, StoredFile } from '@/types/api'

/**
 * Edição da resposta pelo link secreto. O token vem do fragmento (`#token=`),
 * que o navegador não manda ao servidor, e segue para a API num cabeçalho.
 */
const props = defineProps<{
  slug: string
  id: string
}>()

const t = renderer.edit
const route = useRoute()
const queryClient = useQueryClient()

const token = computed(() => new URLSearchParams(route.hash.slice(1)).get('token') ?? '')
const queryKey = computed(() => queryKeys.editableSubmission(props.slug, props.id))

/** Recusas que não são erro de rede: 404 token errado, 409 bloqueada, 410 prazo passou. */
const REFUSALS = [404, 409, 410]

const query = useQuery({
  queryKey,
  queryFn: () => fetchEditableSubmission(props.slug, props.id, token.value),
  enabled: computed(() => token.value !== ''),
  retry: (failures, error) => !REFUSALS.includes(toApiError(error).status) && failures < 1,
  staleTime: Infinity,
})

const submission = computed(() => query.data.value)
const saved = ref(false)
/** Recusa vinda do salvamento (o prazo pode acabar com a página aberta). */
const refusedOnSave = ref<number>()

const refusal = computed<number | null>(() => {
  if (token.value === '') return 404
  if (refusedOnSave.value !== undefined) return refusedOnSave.value
  if (!query.isError.value) return null
  const status = toApiError(query.error.value).status
  return REFUSALS.includes(status) ? status : null
})

const refusalPanel = computed(() => {
  if (refusal.value === 410) return { icon: 'clock' as const, ...t.expired }
  if (refusal.value === 409) return { icon: 'lock' as const, ...t.blocked }
  return { icon: 'link' as const, ...t.invalid }
})

const storedFiles = computed<Record<string, StoredFile[]>>(() =>
  Object.fromEntries((submission.value?.files ?? []).map((group) => [group.field_id, group.files])),
)

const formatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
const editableUntil = computed(() =>
  submission.value?.editable_until
    ? t.until(formatter.format(new Date(submission.value.editable_until)))
    : null,
)

watch(
  () => submission.value?.title,
  (title) => {
    if (title) document.title = `${title} · ${BRAND_NAME}`
  },
  { immediate: true },
)

function onUpdated(value: EditableSubmission): void {
  queryClient.setQueryData(queryKey.value, value)
  saved.value = true
}
</script>

<template>
  <FormPage
    :theme="submission?.schema.theme"
    :title="submission && !saved && refusal === null ? submission.title : undefined"
    :description="submission && !saved && refusal === null ? submission.description : undefined"
  >
    <FormStatusPanel v-if="refusal !== null" :icon="refusalPanel.icon" :title="refusalPanel.title">
      <p class="edit-page__muted">{{ refusalPanel.text }}</p>
    </FormStatusPanel>

    <p v-else-if="query.isPending.value" class="edit-page__state" role="status">
      <AppSpinner />
      {{ renderer.loading }}
    </p>

    <div v-else-if="query.isError.value" class="edit-page__state" role="alert">
      <AppIcon name="circle-alert" />
      <p>{{ renderer.loadError }}</p>
      <AppButton variant="secondary" size="sm" icon-left="rotate-ccw" @click="query.refetch()">
        {{ renderer.retry }}
      </AppButton>
    </div>

    <FormStatusPanel
      v-else-if="saved && submission"
      icon="circle-check"
      :title="t.savedTitle"
      tone="success"
    >
      <p class="edit-page__muted">{{ t.savedText }}</p>
      <AppButton variant="secondary" icon-left="pencil" @click="saved = false">
        {{ t.editAgain }}
      </AppButton>
      <p v-if="editableUntil" class="edit-page__hint">{{ editableUntil }}</p>
    </FormStatusPanel>

    <template v-else-if="submission">
      <p class="edit-page__badge">
        <AppIcon name="pencil" />
        <span>
          <strong>{{ t.badge }}</strong>
          <template v-if="editableUntil"> · {{ editableUntil }}</template>
        </span>
      </p>
      <!-- A chave remonta o formulário com as respostas salvas depois de cada edição. -->
      <PublicFormRenderer
        :key="submission.edited_at ?? 'original'"
        :items="submission.schema.fields"
        :slug="props.slug"
        :button-style="submission.schema.theme?.button_style"
        :editing="{ submissionId: submission.id, token }"
        :initial="submission.answers"
        :stored-files="storedFiles"
        @updated="onUpdated"
        @unavailable="(status) => (refusedOnSave = status)"
      />
    </template>
  </FormPage>
</template>

<style scoped lang="scss">
.edit-page {
  &__state {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: center;
    justify-content: center;
    padding-block: var(--space-16);
    color: var(--color-text-muted);
  }

  &__muted,
  &__hint {
    max-inline-size: var(--dialog-width-md);
    color: var(--color-text-muted);
  }

  &__hint {
    font-size: var(--font-size-xs);
  }

  &__badge {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    padding: var(--space-3);
    margin-block-end: var(--space-2);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    background: var(--color-surface-hover);
    border-radius: var(--radius-md);
  }
}
</style>
