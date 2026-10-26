import type { FormStatus, LegalDocumentKind } from '@/types/api'

/** Chaves do TanStack Query, centralizadas para invalidar após mutações. */
export const queryKeys = {
  legal: (document: LegalDocumentKind) => ['legal', document] as const,
  /** Prefixo de tudo que é formulário: invalidar isto atualiza listas e detalhes. */
  forms: ['forms'] as const,
  formLists: ['forms', 'list'] as const,
  formList: (status: FormStatus | 'all') => ['forms', 'list', status] as const,
  form: (id: string) => ['forms', 'detail', id] as const,
  publicForm: (slug: string) => ['public-form', slug] as const,
}
