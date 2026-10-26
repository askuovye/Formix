import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

import { fetchForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'

import { useBuilderStore } from './useBuilderStore'

/**
 * Formulário aberto no builder, como o servidor o conhece (situação, link,
 * versão publicada). Lê o mesmo cache da página; o rascunho em edição fica
 * nos stores.
 */
export function useBuilderForm() {
  const store = useBuilderStore()

  return useQuery({
    queryKey: computed(() => queryKeys.form(store.formId ?? '')),
    queryFn: () => fetchForm(store.formId ?? ''),
    enabled: computed(() => store.formId !== null),
    staleTime: Infinity,
  })
}
