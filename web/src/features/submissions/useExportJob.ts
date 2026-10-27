import { useQuery } from '@tanstack/vue-query'
import { computed, type Ref } from 'vue'

import { fetchExport } from '@/api/exports'
import { queryKeys } from '@/api/queryKeys'
import type { ExportJob } from '@/types/api'

/** Intervalo entre consultas enquanto o arquivo é gerado (a fila roda a cada minuto). */
export const EXPORT_POLL_MS = 3000

export function isExportActive(job: ExportJob | undefined): boolean {
  return job === undefined || job.status === 'pending' || job.status === 'processing'
}

/** Status de uma exportação, consultado até ficar pronta ou falhar. */
export function useExportJob(id: Ref<string | null>) {
  return useQuery({
    queryKey: computed(() => queryKeys.exportJob(id.value ?? '')),
    queryFn: () => fetchExport(id.value ?? ''),
    enabled: computed(() => id.value !== null),
    refetchInterval: (query) => (isExportActive(query.state.data) ? EXPORT_POLL_MS : false),
    // O link de download é regerado a cada consulta; não reaproveitar um velho.
    staleTime: 0,
  })
}
