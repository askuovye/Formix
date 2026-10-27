import type { DataEnvelope, EditableSubmission, PublicForm, Receipt } from '@/types/api'

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
  /** Segundos entre abrir a página e enviar (só o tempo médio dos resultados). */
  durationSeconds?: number
}

/** Corpo JSON do envio (`duration_seconds` só quando medido). */
type SubmissionJson = Pick<SubmissionPayload, 'answers' | 'website'> & {
  duration_seconds?: number
}

/**
 * Com anexos: multipart com `answers` em texto JSON e `files[<id>][]` por
 * arquivo (docs/api.md); sem anexos, JSON puro.
 */
export function submissionBody(payload: SubmissionPayload): SubmissionJson | FormData {
  const files = Object.entries(payload.files ?? {}).filter(([, list]) => list.length > 0)
  const duration = payload.durationSeconds

  if (files.length === 0) {
    return {
      answers: payload.answers,
      website: payload.website,
      ...(duration === undefined ? {} : { duration_seconds: duration }),
    }
  }

  const body = new FormData()
  body.append('answers', JSON.stringify(payload.answers))
  body.append('website', payload.website)
  if (duration !== undefined) body.append('duration_seconds', String(duration))
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

/** Cabeçalho que leva o token do link de edição (vem do fragmento `#token=`, nunca da URL). */
const EDIT_TOKEN_HEADER = 'X-Edit-Token'

function submissionUrl(slug: string, id: string): string {
  return `/forms/${encodeURIComponent(slug)}/submissions/${encodeURIComponent(id)}`
}

/** Resposta aberta pelo link de edição. */
export async function fetchEditableSubmission(
  slug: string,
  id: string,
  token: string,
): Promise<EditableSubmission> {
  const response = await http.get<DataEnvelope<EditableSubmission>>(submissionUrl(slug, id), {
    headers: { [EDIT_TOKEN_HEADER]: token },
  })

  return response.data.data
}

/**
 * Salva a edição. Com anexos vai em multipart por POST com `_method=PUT` (o
 * PHP só lê multipart em POST); arquivos de um campo substituem os anteriores
 * dele, e campo sem arquivo novo mantém os atuais.
 */
export async function updateSubmission(
  slug: string,
  id: string,
  token: string,
  payload: Pick<SubmissionPayload, 'answers' | 'files'>,
  onProgress?: (fraction: number) => void,
): Promise<EditableSubmission> {
  const body = submissionBody({ ...payload, website: '' })
  const config = {
    headers: { [EDIT_TOKEN_HEADER]: token },
    onUploadProgress: (event: { loaded: number; total?: number }) => {
      if (onProgress && event.total) onProgress(event.loaded / event.total)
    },
  }

  if (body instanceof FormData) {
    body.delete('website')
    body.append('_method', 'PUT')
    const response = await http.post<DataEnvelope<EditableSubmission>>(
      submissionUrl(slug, id),
      body,
      config,
    )
    return response.data.data
  }

  const response = await http.put<DataEnvelope<EditableSubmission>>(
    submissionUrl(slug, id),
    { answers: body.answers },
    config,
  )
  return response.data.data
}
