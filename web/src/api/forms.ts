import type {
  CursorPage,
  DataEnvelope,
  Form,
  FormSettings,
  FormStatus,
  FormSummary,
} from '@/types/api'
import type { FormSchema } from '@/types/schema'

import { http } from './client'

export interface ListFormsParams {
  status?: FormStatus
  cursor?: string | null
  /** Itens por página (≤ 50; padrão do servidor: 20). */
  perPage?: number
  /** Busca no título. */
  search?: string
}

export async function listForms({
  status,
  cursor,
  perPage,
  search,
}: ListFormsParams): Promise<CursorPage<FormSummary>> {
  const response = await http.get<CursorPage<FormSummary>>('/me/forms', {
    params: { status, q: search || undefined, cursor: cursor ?? undefined, per_page: perPage },
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

export interface UpdateFormPayload {
  title?: string
  description?: string | null
  /** Só antes da primeira publicação. */
  slug?: string
  schema?: FormSchema
  /** Parcial: só as chaves enviadas mudam. */
  settings?: Partial<FormSettings>
}

/** Salva o rascunho. O `schema` vai inteiro e substitui o anterior; `settings` valem na hora. */
export async function updateForm(id: string, payload: UpdateFormPayload): Promise<Form> {
  const response = await http.patch<DataEnvelope<Form>>(`/me/forms/${id}`, payload)

  return response.data.data
}

export type FormAssetKind = 'cover' | 'logo'

/** Imagem enviada para o tema (imutável; trocar = enviar outra). */
export interface FormAsset {
  id: string
  kind: FormAssetKind
  url: string
  mime_type: string
  size: number
}

/** Envia capa ou logo (JPG, PNG ou WebP até 5 MB). O tema passa a usá-la pelo `id`. */
export async function uploadFormAsset(
  formId: string,
  file: File,
  kind: FormAssetKind,
): Promise<FormAsset> {
  const body = new FormData()
  body.append('file', file)
  body.append('kind', kind)
  const response = await http.post<DataEnvelope<FormAsset>>(`/me/forms/${formId}/assets`, body)

  return response.data.data
}
