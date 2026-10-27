import type { SubmissionFilters } from '@/api/submissions'
import type { FormStatus, LegalDocumentKind } from '@/types/api'

/** Chaves do TanStack Query, centralizadas para invalidar após mutações. */
export const queryKeys = {
  legal: (document: LegalDocumentKind) => ['legal', document] as const,
  /** Prefixo de tudo que é formulário: invalidar isto atualiza listas e detalhes. */
  forms: ['forms'] as const,
  formLists: ['forms', 'list'] as const,
  formList: (status: FormStatus | 'all', search = '') => ['forms', 'list', status, search] as const,
  form: (id: string) => ['forms', 'detail', id] as const,
  /** Primeira página de formulários para filtros (sob o prefixo: invalida junto). */
  formOptions: ['forms', 'options'] as const,
  /** Prefixo de tudo que é resposta (listas e detalhes). */
  submissions: ['submissions'] as const,
  submissionLists: ['submissions', 'list'] as const,
  submissionList: (filters: SubmissionFilters) => ['submissions', 'list', filters] as const,
  exportJob: (id: string) => ['exports', id] as const,
  submission: (id: string) => ['submissions', 'detail', id] as const,
  publicForm: (slug: string) => ['public-form', slug] as const,
  editableSubmission: (slug: string, id: string) => ['editable-submission', slug, id] as const,
}
