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
