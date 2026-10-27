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

/** Configurações do formulário (valem na hora, sem publicar). Datas em ISO 8601 (UTC). */
export interface FormSettings {
  /** Vazio = email do dono. */
  notification_emails: string[]
  success_message: string | null
  max_submissions: number | null
  opens_at: string | null
  closes_at: string | null
  send_copy_to_respondent: boolean
  /** Id de um campo `email` do formulário (obrigatório se enviar cópia). */
  respondent_email_field: string | null
  allow_respondent_edit: boolean
  retention_days: number | null
}

/** Formulário completo. */
export interface Form extends Omit<FormSummary, 'submissions_count' | 'last_submission_at'> {
  description: string | null
  schema: FormSchema
  settings: FormSettings
}

/** Lista paginada por cursor (`cursorPaginate` do Laravel). */
export interface CursorPage<T> {
  data: T[]
  meta: { per_page: number; next_cursor: string | null; prev_cursor: string | null }
}

/** Situação da página pública (FormAvailability no back-end). */
export type FormAvailability = 'open' | 'closed' | 'not_open_yet' | 'ended' | 'limit_reached'

/** Versão publicada vista pelo respondente (`GET /forms/{slug}`). */
export interface PublicForm {
  slug: string
  version: number
  title: string
  description: string | null
  accepts_submissions: boolean
  availability: FormAvailability
  /** Texto pronto para quem responde quando não aceita respostas. */
  unavailable_message: string | null
  /** `null` quando não aceita respostas. */
  schema: FormSchema | null
  opens_at: string | null
  closes_at: string | null
  allows_respondent_edit: boolean
  privacy_policy_url: string
}

/** Resposta na lista do painel (`GET /me/submissions`). */
export interface SubmissionSummary {
  id: string
  form: { id: string; title: string }
  schema_version: number
  /** Primeiras respostas, para identificar a linha. */
  preview: { label: string; value: string }[]
  files_count: number
  created_at: string
  edited_at: string | null
}

/** Uma resposta já formatada para leitura (na ordem e com os rótulos da versão de envio). */
export interface SubmissionAnswer {
  field_id: string
  label: string
  value: string
  multiline: boolean
  answered: boolean
}

/** Anexos de um campo; `size` já vem legível ("120 KB") e `url` é um link assinado (7 dias). */
export interface SubmissionFileGroup {
  field_id: string
  label: string
  files: { name: string; size: string; url: string | null }[]
}

export interface SubmissionRevision {
  replaced_at: string
  answers: SubmissionAnswer[]
  replaced_files: { field_id: string; name: string; size: number; mime_type: string }[]
}

/** Resposta completa no painel (`GET /me/submissions/{id}`). */
export interface Submission {
  id: string
  form: { id: string; title: string; slug: string }
  schema_version: number
  answers: SubmissionAnswer[]
  files: SubmissionFileGroup[]
  /** Versões anteriores, da mais recente para a mais antiga. */
  revisions: SubmissionRevision[]
  created_at: string
  edited_at: string | null
  editable_until: string | null
}

export type ExportFormat = 'csv' | 'pdf'

/** Exportação de respostas gerada na fila (`GET /me/exports/{id}`). */
export interface ExportJob {
  id: string
  form_id: string
  status: 'pending' | 'processing' | 'ready' | 'failed'
  format: ExportFormat
  from: string | null
  to: string | null
  search: string | null
  rows_count: number | null
  /** Só quando pronta; link assinado curto, regerado a cada consulta. */
  download_url: string | null
  created_at: string
  completed_at: string | null
  expires_at: string | null
}

/** Anexo já guardado numa resposta (só nome e tamanho em bytes). */
export interface StoredFile {
  name: string
  size: number
}

/** Resposta aberta pelo link de edição (`GET /forms/{slug}/submissions/{id}`). */
export interface EditableSubmission {
  id: string
  version: number
  title: string
  description: string | null
  /** Schema da versão com que a resposta foi enviada. */
  schema: FormSchema
  answers: Record<string, unknown>
  /** Anexos atuais por campo, na ordem do schema. */
  files: { field_id: string; files: StoredFile[] }[]
  editable_until: string | null
  edited_at: string | null
}

/** Comprovante de envio de uma resposta. */
export interface Receipt {
  id: string
  success_message: string
  /** Link secreto de edição (só com a edição ativa). */
  edit_url: string | null
  editable_until: string | null
}
