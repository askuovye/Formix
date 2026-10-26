import type { DataEnvelope, LegalDocument, LegalDocumentKind } from '@/types/api'

import { http } from './client'

export async function fetchLegalDocument(document: LegalDocumentKind): Promise<LegalDocument> {
  const response = await http.get<DataEnvelope<LegalDocument>>(`/legal/${document}`)

  return response.data.data
}
