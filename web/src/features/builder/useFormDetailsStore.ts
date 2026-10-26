import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'

import type { Form } from '@/types/api'

import {
  type DetailsErrors,
  type FormDetails,
  detailsFromForm,
  detailsPayload,
  validateDetails,
} from './details'
import { useBuilderStore } from './useBuilderStore'

/**
 * Rascunho dos dados gerais do formulário (título, descrição, endereço e
 * configurações). Fica fora do histórico de desfazer, que é só dos itens.
 * Salvo pelo mesmo salvamento automático do builder.
 */
export const useFormDetailsStore = defineStore('formDetails', () => {
  const details = ref<FormDetails | null>(null)
  /** O endereço só muda antes da primeira publicação. */
  const slugEditable = ref(false)
  /** Sobe a cada mudança; o salvamento automático observa. */
  const revision = ref(0)
  const serverErrors = shallowRef<DetailsErrors>({})

  const builderStore = useBuilderStore()

  const clientErrors = computed<DetailsErrors>(() =>
    details.value ? validateDetails(details.value, builderStore.items, slugEditable.value) : {},
  )
  const hasClientErrors = computed(() => Object.keys(clientErrors.value).length > 0)
  /** Locais têm prioridade (refletem o que está na tela agora). */
  const errors = computed<DetailsErrors>(() => ({ ...serverErrors.value, ...clientErrors.value }))

  function load(form: Form): void {
    details.value = detailsFromForm(form)
    slugEditable.value = form.published_version === 0
    serverErrors.value = {}
    revision.value++
  }

  /** Após publicar ou salvar: só a trava do endereço muda (o rascunho em edição fica). */
  function syncFromServer(form: Form): void {
    slugEditable.value = form.published_version === 0
  }

  /** Altera o rascunho; `key` (caminho do erro) limpa o erro do servidor daquele campo. */
  function update(recipe: (draft: FormDetails) => void, key?: string): void {
    if (!details.value) return

    recipe(details.value)
    revision.value++

    if (key && key in serverErrors.value) {
      serverErrors.value = Object.fromEntries(
        Object.entries(serverErrors.value).filter(([path]) => path !== key),
      )
    }
  }

  function setServerErrors(next: DetailsErrors): void {
    serverErrors.value = next
  }

  function payload() {
    return details.value ? detailsPayload(details.value, slugEditable.value) : {}
  }

  return {
    details,
    slugEditable,
    revision,
    serverErrors,
    clientErrors,
    hasClientErrors,
    errors,
    load,
    syncFromServer,
    update,
    setServerErrors,
    payload,
  }
})
