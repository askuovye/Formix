<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'

import { toApiError } from '@/api/client'
import { fetchPublicForm } from '@/api/publicForms'
import { queryKeys } from '@/api/queryKeys'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { BRAND_NAME } from '@/config/brand'
import FormReceipt from '@/features/renderer/FormReceipt.vue'
import FormStatusPanel from '@/features/renderer/FormStatusPanel.vue'
import PublicFormRenderer from '@/features/renderer/PublicFormRenderer.vue'
import { renderer } from '@/strings'
import type { Receipt } from '@/types/api'

/** Página pública de um formulário: carrega a versão publicada e mostra o estado certo. */
const props = defineProps<{
  slug: string
}>()

const queryClient = useQueryClient()
const query = useQuery({
  queryKey: computed(() => queryKeys.publicForm(props.slug)),
  queryFn: () => fetchPublicForm(props.slug),
  // 404 é resposta definitiva; o resto (rede, 5xx) tenta de novo uma vez.
  retry: (failures, error) => toApiError(error).status !== 404 && failures < 1,
  staleTime: Infinity,
})

const receipt = ref<Receipt | null>(null)
const form = computed(() => query.data.value)
const notFound = computed(() => query.isError.value && toApiError(query.error.value).status === 404)

const dateFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long', timeStyle: 'short' })
const opensAt = computed(() =>
  form.value?.availability === 'not_open_yet' && form.value.opens_at
    ? renderer.unavailable.opensAt(dateFormatter.format(new Date(form.value.opens_at)))
    : null,
)

watch(
  () => form.value?.title,
  (title) => {
    if (title) document.title = `${title} · ${BRAND_NAME}`
  },
  { immediate: true },
)

function reload(): void {
  void queryClient.invalidateQueries({ queryKey: queryKeys.publicForm(props.slug) })
}
</script>

<template>
  <div class="public-form-page">
    <p v-if="query.isPending.value" class="public-form-page__state" role="status">
      <AppSpinner />
      {{ renderer.loading }}
    </p>

    <FormStatusPanel v-else-if="notFound" icon="file-text" :title="renderer.notFound.title">
      <p class="public-form-page__muted">{{ renderer.notFound.text }}</p>
    </FormStatusPanel>

    <div v-else-if="query.isError.value" class="public-form-page__state" role="alert">
      <AppIcon name="circle-alert" />
      <p>{{ renderer.loadError }}</p>
      <AppButton variant="secondary" size="sm" icon-left="rotate-ccw" @click="query.refetch()">
        {{ renderer.retry }}
      </AppButton>
    </div>

    <FormReceipt v-else-if="receipt" :receipt="receipt" />

    <template v-else-if="form">
      <header class="public-form-page__header">
        <h1 class="public-form-page__title">{{ form.title }}</h1>
        <p v-if="form.description" class="public-form-page__description">
          {{ form.description }}
        </p>
      </header>

      <PublicFormRenderer
        v-if="form.accepts_submissions && form.schema"
        :form="form"
        :items="form.schema.fields"
        @submitted="(value) => (receipt = value)"
        @unavailable="reload"
      />

      <FormStatusPanel
        v-else
        :icon="form.availability === 'not_open_yet' ? 'clock' : 'lock'"
        :title="renderer.unavailable.title[form.availability]"
      >
        <p class="public-form-page__muted">{{ form.unavailable_message }}</p>
        <p v-if="opensAt" class="public-form-page__muted">{{ opensAt }}</p>
      </FormStatusPanel>
    </template>
  </div>
</template>

<style scoped lang="scss">
.public-form-page {
  display: grid;
  gap: var(--space-8);

  &__state {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: center;
    justify-content: center;
    padding-block: var(--space-16);
    color: var(--color-text-muted);
  }

  &__header {
    display: grid;
    gap: var(--space-3);
  }

  &__title {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
    overflow-wrap: anywhere;
  }

  &__description {
    color: var(--color-text-muted);
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  &__muted {
    max-inline-size: var(--dialog-width-md);
    color: var(--color-text-muted);
  }
}
</style>
