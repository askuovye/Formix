import type { FormSchema } from './schema'

/** DTOs da API (docs/api.md). */

export interface User {
  id: string
  name: string
  email: string
  email_verified: boolean
  created_at: string
}

export type LegalDocumentKind = 'terms' | 'privacy'

export interface LegalDocument {
  document: LegalDocumentKind
  title: string
  version: string
  updated_at: string
  /** HTML gerado e escapado pela API (D34): seguro para v-html. */
  content_html: string
}

export interface DataEnvelope<T> {
  data: T
}

export interface MessageResponse {
  message: string
}

export type FormStatus = 'draft' | 'published' | 'closed'

/** Formulário na listagem (sem schema nem configurações). */
export interface FormSummary {
  id: string
  title: string
  slug: string
  status: FormStatus
  published_version: number
  has_unpublished_changes: boolean
  submissions_count: number
  last_submission_at: string | null
  public_url: string
  published_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
}

/** Formulário completo. As configurações ganham tipo próprio na aba de configurações (fase 3d). */
export interface Form extends Omit<FormSummary, 'submissions_count' | 'last_submission_at'> {
  description: string | null
  schema: FormSchema
  settings: unknown
}

/** Lista paginada por cursor (`cursorPaginate` do Laravel). */
export interface CursorPage<T> {
  data: T[]
  meta: { per_page: number; next_cursor: string | null; prev_cursor: string | null }
}
