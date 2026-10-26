import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { toApiError } from '@/api/client'
import { closeForm, deleteForm, publishForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import { useToastStore } from '@/stores/useToastStore'
import { forms } from '@/strings'

/** Arquivar (fechar), reabrir (publicar de novo), excluir e copiar o link de um formulário. */
export function useFormActions() {
  const queryClient = useQueryClient()
  const toasts = useToastStore()

  function mutationFor(mutationFn: (id: string) => Promise<unknown>, success: string) {
    return useMutation({
      mutationFn,
      onSuccess: async () => {
        toasts.show({ title: success, tone: 'success' })
        await queryClient.invalidateQueries({ queryKey: queryKeys.forms })
      },
      onError: (error) => toasts.show({ title: toApiError(error).message, tone: 'danger' }),
    })
  }

  const archive = mutationFor(closeForm, forms.toasts.archived)
  const reopen = mutationFor(publishForm, forms.toasts.reopened)
  const remove = mutationFor(deleteForm, forms.toasts.deleted)

  async function copyLink(url: string): Promise<void> {
    try {
      await navigator.clipboard.writeText(url)
      toasts.show({ title: forms.toasts.linkCopied, tone: 'success' })
    } catch {
      // Sem permissão de área de transferência: mostra o link para copiar à mão.
      toasts.show({ title: forms.toasts.copyFailed, description: url })
    }
  }

  return { archive, reopen, remove, copyLink }
}
