import type { CursorPage, DataEnvelope, Submission, SubmissionSummary } from '@/types/api'

import { http } from './client'

/** Filtros da lista de respostas (`from` em `AAAA-MM-DD`, dia inteiro em Brasília). */
export interface SubmissionFilters {
  form?: string
  from?: string
  sort: 'newest' | 'oldest'
}

export async function listSubmissions(
  filters: SubmissionFilters,
  cursor: string | null,
): Promise<CursorPage<SubmissionSummary>> {
  const response = await http.get<CursorPage<SubmissionSummary>>('/me/submissions', {
    params: { ...filters, cursor: cursor ?? undefined },
  })

  return response.data
}

export async function fetchSubmission(id: string): Promise<Submission> {
  const response = await http.get<DataEnvelope<Submission>>(`/me/submissions/${id}`)

  return response.data.data
}

/** Exclusão definitiva (apaga também os anexos). */
export async function deleteSubmission(id: string): Promise<void> {
  await http.delete(`/me/submissions/${id}`)
}
