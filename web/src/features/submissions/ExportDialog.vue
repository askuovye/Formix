<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'

import { toApiError, type ApiError } from '@/api/client'
import { requestExport } from '@/api/exports'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppRadioGroup from '@/components/AppRadioGroup.vue'
import AppSelect from '@/components/AppSelect.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { useExportsStore } from '@/stores/useExportsStore'
import { submissions } from '@/strings'
import type { ExportFormat } from '@/types/api'

import { isExportActive, useExportJob } from './useExportJob'

/**
 * Exportar respostas em CSV ou PDF. O arquivo é gerado na fila: a janela
 * acompanha o status e, se for fechada antes, um aviso chega quando terminar.
 */
const props = defineProps<{
  /** Formulários que podem ser escolhidos (um só = fixo, sem seletor). */
  forms: { value: string; label: string }[]
  /** Formulário já escolhido (filtro atual da lista). */
  initialFormId?: string
  /** Início do período, em `AAAA-MM-DD`, e o rótulo para o resumo. */
  from?: string
  periodLabel?: string
  search?: string
}>()

const open = defineModel<boolean>('open', { default: false })

const t = submissions.export
const exportsStore = useExportsStore()

const formId = ref<string | undefined>()
const format = ref<ExportFormat>('csv')
const jobId = ref<string | null>(null)

const formTitle = computed(
  () => props.forms.find((form) => form.value === formId.value)?.label ?? '',
)

const formatOptions = computed(() => [
  { value: 'csv', label: t.formats.csv, hint: t.formats.csvHint },
  { value: 'pdf', label: t.formats.pdf, hint: t.formats.pdfHint },
])

const mutation = useMutation({
  mutationFn: () => {
    if (!formId.value) throw new Error(t.chooseForm)

    return requestExport(formId.value, {
      format: format.value,
      ...(props.from ? { from: props.from } : {}),
      ...(props.search ? { q: props.search } : {}),
    })
  },
  onSuccess: (job) => {
    jobId.value = job.id
    exportsStore.track(job, formTitle.value)
  },
})

// Cada abertura começa do zero, com o formulário do filtro atual.
watch(
  open,
  (isOpen) => {
    if (isOpen) {
      formId.value =
        props.initialFormId ?? (props.forms.length === 1 ? props.forms[0]?.value : undefined)
      format.value = 'csv'
      jobId.value = null
      mutation.reset()
    }
    exportsStore.visibleId = isOpen ? jobId.value : null
  },
  { immediate: true },
)

watch(jobId, (id) => {
  if (open.value) exportsStore.visibleId = id
})

const error = computed<ApiError | null>(() =>
  mutation.error.value ? toApiError(mutation.error.value) : null,
)
const formatError = computed(() => error.value?.firstFieldErrors.format)

const jobQuery = useExportJob(jobId)
const job = computed(() => jobQuery.data.value)
const working = computed(() => jobId.value !== null && isExportActive(job.value))

function onFormat(value: string | undefined): void {
  if (value === 'csv' || value === 'pdf') format.value = value
}
</script>

<template>
  <AppDialog v-model:open="open" :title="t.title" :description="t.description">
    <!-- Antes de pedir: formulário, formato e o que vai junto. -->
    <div v-if="jobId === null" class="export-dialog">
      <AppSelect
        v-if="props.forms.length > 1"
        v-model="formId"
        :label="t.form"
        :placeholder="t.chooseForm"
        :options="props.forms"
      />

      <AppRadioGroup
        :model-value="format"
        :label="t.format"
        :options="formatOptions"
        :error="formatError"
        @update:model-value="onFormat"
      />

      <dl class="export-dialog__summary">
        <div class="export-dialog__row">
          <dt class="export-dialog__term">{{ t.period }}</dt>
          <dd class="export-dialog__value">{{ props.periodLabel ?? t.allTime }}</dd>
        </div>
        <div v-if="props.search" class="export-dialog__row">
          <dt class="export-dialog__term">{{ t.search }}</dt>
          <dd class="export-dialog__value">“{{ props.search }}”</dd>
        </div>
      </dl>

      <AppAlert v-if="error && !formatError" tone="danger">{{ error.message }}</AppAlert>
    </div>

    <!-- Depois: acompanhando a fila. -->
    <div v-else class="export-dialog__status" aria-live="polite">
      <template v-if="working">
        <AppSpinner class="export-dialog__spinner" />
        <p class="export-dialog__title">{{ t.preparing }}</p>
        <p class="export-dialog__text">{{ t.preparingHint }}</p>
      </template>
      <AppAlert v-else-if="job?.status === 'failed'" tone="danger">{{ t.failed }}</AppAlert>
      <template v-else-if="job">
        <p class="export-dialog__title">{{ t.ready }}</p>
        <p class="export-dialog__text">{{ t.rows(job.rows_count ?? 0) }}</p>
      </template>
    </div>

    <template #footer="{ close }">
      <template v-if="jobId === null">
        <AppButton variant="ghost" @click="close">{{ t.cancel }}</AppButton>
        <AppButton
          icon-left="download"
          :loading="mutation.isPending.value"
          :disabled="!formId"
          @click="mutation.mutate()"
        >
          {{ t.submit }}
        </AppButton>
      </template>
      <template v-else>
        <AppButton variant="ghost" @click="close">{{ t.close }}</AppButton>
        <AppButton
          v-if="job?.status === 'ready' && job.download_url"
          icon-left="download"
          :href="job.download_url"
        >
          {{ t.download[job.format] }}
        </AppButton>
      </template>
    </template>
  </AppDialog>
</template>

<style scoped lang="scss">
.export-dialog {
  display: grid;
  gap: var(--space-5);

  &__summary {
    display: grid;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    font-size: var(--font-size-sm);
    background: var(--color-surface-raised);
    border-radius: var(--radius-md);
  }

  &__row {
    display: flex;
    gap: var(--space-3);
    justify-content: space-between;
  }

  &__term {
    color: var(--color-text-muted);
  }

  &__value {
    font-weight: var(--font-weight-medium);
    text-align: end;
    overflow-wrap: anywhere;
  }

  &__status {
    display: grid;
    gap: var(--space-2);
    justify-items: center;
    padding-block: var(--space-6);
    text-align: center;
  }

  &__spinner {
    font-size: var(--font-size-2xl);
    color: var(--color-text-muted);
  }

  &__title {
    font-weight: var(--font-weight-semibold);
  }

  &__text {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
