import type { DataEnvelope, PublicForm, Receipt } from '@/types/api'

import { http } from './client'

/** Página pública do formulário (sem login). */
export async function fetchPublicForm(slug: string): Promise<PublicForm> {
  const response = await http.get<DataEnvelope<PublicForm>>(`/forms/${encodeURIComponent(slug)}`)

  return response.data.data
}

export interface SubmissionPayload {
  /** Respostas por id de campo. */
  answers: Record<string, unknown>
  /** Honeypot: sempre vazio para pessoas. */
  website: string
  /** Anexos por id de campo; com algum, o envio vira multipart. */
  files?: Record<string, File[]>
}

/**
 * Com anexos: multipart com `answers` em texto JSON e `files[<id>][]` por
 * arquivo (docs/api.md); sem anexos, JSON puro.
 */
export function submissionBody(payload: SubmissionPayload): SubmissionPayload | FormData {
  const files = Object.entries(payload.files ?? {}).filter(([, list]) => list.length > 0)
  if (files.length === 0) return { answers: payload.answers, website: payload.website }

  const body = new FormData()
  body.append('answers', JSON.stringify(payload.answers))
  body.append('website', payload.website)
  for (const [fieldId, list] of files) {
    for (const file of list) body.append(`files[${fieldId}][]`, file, file.name)
  }

  return body
}

/**
 * Envia a resposta. A mesma `idempotencyKey` em retentativas devolve o mesmo
 * comprovante, sem duplicar. `onProgress` recebe a fração enviada (0 a 1).
 */
export async function submitAnswers(
  slug: string,
  payload: SubmissionPayload,
  idempotencyKey: string,
  onProgress?: (fraction: number) => void,
): Promise<Receipt> {
  const response = await http.post<DataEnvelope<Receipt>>(
    `/forms/${encodeURIComponent(slug)}/submissions`,
    submissionBody(payload),
    {
      headers: { 'Idempotency-Key': idempotencyKey },
      onUploadProgress: (event) => {
        if (onProgress && event.total) onProgress(event.loaded / event.total)
      },
    },
  )

  return response.data.data
}
