import type { LegalDocumentKind } from '@/types/api'

/** Chaves do TanStack Query, centralizadas para invalidar após mutações. */
export const queryKeys = {
  legal: (document: LegalDocumentKind) => ['legal', document] as const,
}
