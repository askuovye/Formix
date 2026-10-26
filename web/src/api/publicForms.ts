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
}

/**
 * Envia a resposta. A mesma `idempotencyKey` em retentativas devolve o mesmo
 * comprovante, sem duplicar.
 */
export async function submitAnswers(
  slug: string,
  payload: SubmissionPayload,
  idempotencyKey: string,
): Promise<Receipt> {
  const response = await http.post<DataEnvelope<Receipt>>(
    `/forms/${encodeURIComponent(slug)}/submissions`,
    payload,
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )

  return response.data.data
}
