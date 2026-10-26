<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

import { ApiError } from '@/api/client'
import { fetchForm, updateForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppSkipLink from '@/components/AppSkipLink.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import BuilderTopbar from '@/features/builder/BuilderTopbar.vue'
import BuilderWorkspace from '@/features/builder/BuilderWorkspace.vue'
import { mapServerErrors } from '@/features/builder/schemaErrors'
import { InvalidDraftError, useAutosave } from '@/features/builder/useAutosave'
import { useBuilderStore } from '@/features/builder/useBuilderStore'
import { builder, common } from '@/strings'

const props = defineProps<{
  id: string
}>()

const store = useBuilderStore()
const queryClient = useQueryClient()

const query = useQuery({
  queryKey: computed(() => queryKeys.form(props.id)),
  queryFn: () => fetchForm(props.id),
  // O rascunho em edição vive no store; não recarregar por cima dele.
  staleTime: Infinity,
})

async function save(): Promise<void> {
  // Erro local (campo vazio, número fora do limite…): nem envia; o item já mostra o erro.
  if (store.hasClientErrors) {
    throw new InvalidDraftError()
  }

  const sent = store.snapshot()

  try {
    const form = await updateForm(props.id, { schema: { ...store.schema, fields: sent } })
    store.setServerErrors(new Map())
    store.adoptServerRules(sent, form.schema.fields)
    // Atualiza situação e "alterações não publicadas" sem trocar o rascunho em edição.
    queryClient.setQueryData(queryKeys.form(props.id), form)
    await queryClient.invalidateQueries({ queryKey: queryKeys.formLists })
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      store.setServerErrors(mapServerErrors(error.fieldErrors, sent))
    }
    throw error
  }
}

const autosave = useAutosave(() => store.revision, save)

// Carrega no store quando o formulário chega (ou quando muda de formulário).
watch(
  () => query.data.value,
  (form) => {
    if (form && store.formId !== form.id) {
      store.load(form.id, form.schema)
      autosave.reset(store.revision)
    }
  },
  { immediate: true },
)

const paletteOpen = ref(false)
const propertiesOpen = ref(false)

// Sair do editor salva o que estiver pendente antes.
onBeforeRouteLeave(async () => {
  await autosave.flush()
})

function warnUnsaved(event: BeforeUnloadEvent): void {
  if (autosave.hasUnsaved()) {
    event.preventDefault()
  }
}

onMounted(() => window.addEventListener('beforeunload', warnUnsaved))
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnUnsaved)
  // Na próxima abertura o store recarrega do servidor (o salvo é a fonte da verdade).
  store.formId = null
})
</script>

<template>
  <div class="builder-page">
    <AppSkipLink target="conteudo" />

    <div v-if="query.isPending.value" class="builder-page__status" role="status">
      <AppSpinner />
      {{ common.loading }}
    </div>

    <div v-else-if="query.isError.value" class="builder-page__status">
      <AppAlert tone="danger">{{ builder.loadError }}</AppAlert>
      <AppButton variant="secondary" @click="query.refetch()">{{ builder.retry }}</AppButton>
      <AppButton :to="{ name: 'forms' }" variant="ghost" icon-left="arrow-left">
        {{ builder.back }}
      </AppButton>
    </div>

    <template v-else-if="query.data.value">
      <BuilderTopbar
        :title="query.data.value.title"
        :status="query.data.value.status"
        :save-status="autosave.status.value"
        @retry-save="autosave.flush()"
        @open-properties="propertiesOpen = true"
      />
      <BuilderWorkspace
        v-model:palette-open="paletteOpen"
        v-model:properties-open="propertiesOpen"
        class="builder-page__workspace"
      />
    </template>
  </div>
</template>

<style scoped lang="scss">
.builder-page {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  grid-template-rows: auto minmax(0, 1fr);
  height: 100dvh;
  background: var(--color-bg);

  &__status {
    display: grid;
    grid-row: 1 / -1;
    gap: var(--space-4);
    place-content: center;
    justify-items: center;
    padding: var(--space-4);
    color: var(--color-text-muted);
  }

  &__workspace {
    min-height: 0;
  }
}
</style>
