import { useQueryClient } from '@tanstack/vue-query'
import { ref, toValue, type MaybeRefOrGetter, type Ref } from 'vue'

import { toApiError } from '@/api/client'
import { closeForm, publishForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import { useToastStore } from '@/stores/useToastStore'
import { builder } from '@/strings'
import type { Form } from '@/types/api'

import { detailsServerErrors } from './details'
import type { SaveStatus } from './useAutosave'
import { useFormDetailsStore } from './useFormDetailsStore'

interface AutosaveHandle {
  status: Ref<SaveStatus>
  flush: () => Promise<void>
}

/**
 * Publicar (ou reabrir) e fechar o formulário do builder. Antes de publicar,
 * salva o rascunho: o servidor publica o que está salvo.
 */
export function usePublishing(formId: MaybeRefOrGetter<string>, autosave: AutosaveHandle) {
  const queryClient = useQueryClient()
  const toasts = useToastStore()
  const detailsStore = useFormDetailsStore()
  const t = builder.publish.toasts

  const publishing = ref(false)
  const closing = ref(false)

  async function adopt(form: Form): Promise<void> {
    queryClient.setQueryData(queryKeys.form(form.id), form)
    detailsStore.syncFromServer(form)
    await queryClient.invalidateQueries({ queryKey: queryKeys.formLists })
  }

  async function publish(): Promise<void> {
    if (publishing.value) return

    publishing.value = true

    try {
      await autosave.flush()

      if (autosave.status.value === 'invalid') {
        toasts.show({ title: t.fixFirst, tone: 'danger' })
        return
      }
      if (autosave.status.value === 'error') {
        toasts.show({ title: t.saveFirst, tone: 'danger' })
        return
      }

      const id = toValue(formId)
      const wasClosed = queryClient.getQueryData<Form>(queryKeys.form(id))?.status === 'closed'
      const form = await publishForm(id)
      await adopt(form)
      toasts.show({
        title: wasClosed ? t.reopened : t.published,
        description: t.publishedDescription,
        tone: 'success',
      })
    } catch (error) {
      const apiError = toApiError(error)

      // 422: sem campos ou configuração incoerente (ex.: campo de e-mail da cópia).
      if (apiError.status === 422) {
        detailsStore.setServerErrors(detailsServerErrors(apiError.fieldErrors))
      }
      const first = Object.values(apiError.fieldErrors)[0]?.[0]
      toasts.show({ title: first ?? apiError.message, tone: 'danger' })
    } finally {
      publishing.value = false
    }
  }

  /** Fecha para respostas. Devolve `true` se fechou (a confirmação some). */
  async function close(): Promise<boolean> {
    closing.value = true

    try {
      await adopt(await closeForm(toValue(formId)))
      toasts.show({ title: t.closed, tone: 'success' })
      return true
    } catch (error) {
      toasts.show({ title: toApiError(error).message, tone: 'danger' })
      return false
    } finally {
      closing.value = false
    }
  }

  return { publishing, closing, publish, close }
}
