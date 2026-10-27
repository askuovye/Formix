import type { DataEnvelope, FormResults } from '@/types/api'

import { http } from './client'

/** Mesmos filtros da lista de respostas (`from` em `AAAA-MM-DD`). */
export interface ResultsFilters {
  from?: string
  q?: string
  email?: string
}

export async function fetchResults(formId: string, filters: ResultsFilters): Promise<FormResults> {
  const response = await http.get<DataEnvelope<FormResults>>(`/me/forms/${formId}/results`, {
    params: filters,
  })

  return response.data.data
}
