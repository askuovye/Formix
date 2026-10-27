import { defineStore } from 'pinia'
import { ref } from 'vue'

import type { ExportJob } from '@/types/api'

export interface TrackedExport {
  id: string
  formTitle: string
}

/**
 * Exportações pedidas nesta sessão e ainda não concluídas. O status vem do
 * servidor (TanStack Query, em ExportTracker); aqui fica só o que acompanhar
 * e qual delas está aberta na janela (essa não precisa de aviso).
 */
export const useExportsStore = defineStore('exports', () => {
  const tracked = ref<TrackedExport[]>([])
  const visibleId = ref<string | null>(null)

  function track(job: ExportJob, formTitle: string): void {
    if (!tracked.value.some((item) => item.id === job.id)) {
      tracked.value.push({ id: job.id, formTitle })
    }
  }

  function untrack(id: string): void {
    tracked.value = tracked.value.filter((item) => item.id !== id)
  }

  return { tracked, visibleId, track, untrack }
})
