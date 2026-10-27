import type { DataEnvelope, ExportFormat, ExportJob } from '@/types/api'

import { http } from './client'

/** Pedido de exportação: mesmos filtros da lista (`from` em `AAAA-MM-DD`). */
export interface ExportRequest {
  format: ExportFormat
  from?: string
  q?: string
}

/** Enfileira a geração do arquivo; acompanhar por `fetchExport` até `ready`. */
export async function requestExport(formId: string, body: ExportRequest): Promise<ExportJob> {
  const response = await http.post<DataEnvelope<ExportJob>>(`/me/forms/${formId}/exports`, body)

  return response.data.data
}

export async function fetchExport(id: string): Promise<ExportJob> {
  const response = await http.get<DataEnvelope<ExportJob>>(`/me/exports/${id}`)

  return response.data.data
}
