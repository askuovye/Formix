import type { CursorPage, DataEnvelope, Form, FormStatus, FormSummary } from '@/types/api'

import { http } from './client'

export interface ListFormsParams {
  status?: FormStatus
  cursor?: string | null
}

export async function listForms({
  status,
  cursor,
}: ListFormsParams): Promise<CursorPage<FormSummary>> {
  const response = await http.get<CursorPage<FormSummary>>('/me/forms', {
    params: { status, cursor: cursor ?? undefined },
  })

  return response.data
}

export async function createForm(payload: { title: string }): Promise<Form> {
  const response = await http.post<DataEnvelope<Form>>('/me/forms', payload)

  return response.data.data
}

export async function fetchForm(id: string): Promise<Form> {
  const response = await http.get<DataEnvelope<Form>>(`/me/forms/${id}`)

  return response.data.data
}

export async function deleteForm(id: string): Promise<void> {
  await http.delete(`/me/forms/${id}`)
}

/** Publica o rascunho atual. Também reabre um formulário fechado. */
export async function publishForm(id: string): Promise<Form> {
  const response = await http.post<DataEnvelope<Form>>(`/me/forms/${id}/publish`)

  return response.data.data
}

export async function closeForm(id: string): Promise<Form> {
  const response = await http.post<DataEnvelope<Form>>(`/me/forms/${id}/close`)

  return response.data.data
}
