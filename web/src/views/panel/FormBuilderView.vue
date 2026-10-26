<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

import { ApiError } from '@/api/client'
import { fetchForm, updateForm, type UpdateFormPayload } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppSkipLink from '@/components/AppSkipLink.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import BuilderTopbar from '@/features/builder/BuilderTopbar.vue'
import BuilderWorkspace from '@/features/builder/BuilderWorkspace.vue'
import { detailsServerErrors } from '@/features/builder/details'
import { mapServerErrors } from '@/features/builder/schemaErrors'
import type { BuilderSection } from '@/features/builder/sections'
import { InvalidDraftError, useAutosave } from '@/features/builder/useAutosave'
import { useBuilderStore } from '@/features/builder/useBuilderStore'
import { useFormDetailsStore } from '@/features/builder/useFormDetailsStore'
import { usePublishing } from '@/features/builder/usePublishing'
import { builder, common } from '@/strings'

const props = defineProps<{
  id: string
}>()

const store = useBuilderStore()
const detailsStore = useFormDetailsStore()
const queryClient = useQueryClient()

const query = useQuery({
  queryKey: computed(() => queryKeys.form(props.id)),
  queryFn: () => fetchForm(props.id),
  // O rascunho em edição vive no store; não recarregar por cima dele.
  staleTime: Infinity,
})

// Última revisão salva de cada parte: só vai ao servidor o que mudou.
let savedItemsRevision = 0
let savedDetailsRevision = 0

async function save(): Promise<void> {
  // Erro local (campo vazio, número fora do limite…): nem envia; o item já mostra o erro.
  if (store.hasClientErrors || detailsStore.hasClientErrors) {
    throw new InvalidDraftError()
  }

  const itemsRevision = store.revision
  const detailsRevision = detailsStore.revision
  const payload: UpdateFormPayload = {}
  let sent: ReturnType<typeof store.snapshot> | null = null

  if (itemsRevision !== savedItemsRevision) {
    sent = store.snapshot()
    payload.schema = { ...store.schema, fields: sent }
  }
  if (detailsRevision !== savedDetailsRevision) {
    Object.assign(payload, detailsStore.payload())
  }

  try {
    const form = await updateForm(props.id, payload)
    savedItemsRevision = itemsRevision
    savedDetailsRevision = detailsRevision
    store.setServerErrors(new Map())
    detailsStore.setServerErrors({})
    if (sent) store.adoptServerRules(sent, form.schema.fields)
    detailsStore.syncFromServer(form)
    // Atualiza situação e "alterações não publicadas" sem trocar o rascunho em edição.
    queryClient.setQueryData(queryKeys.form(props.id), form)
    await queryClient.invalidateQueries({ queryKey: queryKeys.formLists })
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      store.setServerErrors(mapServerErrors(schemaErrorsOf(error.fieldErrors), sent ?? store.items))
      detailsStore.setServerErrors(detailsServerErrors(error.fieldErrors))
    }
    throw error
  }
}

function schemaErrorsOf(fieldErrors: Record<string, string[]>): Record<string, string[]> {
  return Object.fromEntries(
    Object.entries(fieldErrors).filter(([path]) => path === 'schema' || path.startsWith('schema.')),
  )
}

const autosave = useAutosave(() => store.revision + detailsStore.revision, save)

// Carrega nos stores quando o formulário chega (ou quando muda de formulário).
watch(
  () => query.data.value,
  (form) => {
    if (form && store.formId !== form.id) {
      store.load(form.id, form.schema)
      detailsStore.load(form)
      savedItemsRevision = store.revision
      savedDetailsRevision = detailsStore.revision
      autosave.reset(store.revision + detailsStore.revision)
    }
  },
  { immediate: true },
)

const publishing = usePublishing(() => props.id, autosave)

const menuOpen = ref(false)
const propertiesOpen = ref(false)
const section = ref<BuilderSection>('fields')

async function close(done: (closed: boolean) => void): Promise<void> {
  done(await publishing.close())
}

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
        :title="detailsStore.details?.title || query.data.value.title"
        :form="query.data.value"
        :save-status="autosave.status.value"
        :publishing="publishing.publishing.value"
        :closing="publishing.closing.value"
        @retry-save="autosave.flush()"
        @open-menu="menuOpen = true"
        @open-properties="propertiesOpen = true"
        @publish="publishing.publish()"
        @close="close"
      />
      <BuilderWorkspace
        v-model:menu-open="menuOpen"
        v-model:properties-open="propertiesOpen"
        v-model:section="section"
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
