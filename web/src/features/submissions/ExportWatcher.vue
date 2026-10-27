<script setup lang="ts">
import { toRef, watch } from 'vue'

import { useExportsStore, type TrackedExport } from '@/stores/useExportsStore'
import { useToastStore } from '@/stores/useToastStore'
import { submissions } from '@/strings'

import { useExportJob } from './useExportJob'

/** Acompanha uma exportação e avisa quando termina (sem UI própria). */
const props = defineProps<{
  item: TrackedExport
}>()

const t = submissions.export
const exportsStore = useExportsStore()
const toasts = useToastStore()

const query = useExportJob(toRef(() => props.item.id))

watch(
  () => query.data.value,
  (job) => {
    if (!job || (job.status !== 'ready' && job.status !== 'failed')) return

    // Aberta na janela: ela mesma mostra o resultado.
    if (exportsStore.visibleId !== job.id) {
      if (job.status === 'ready' && job.download_url) {
        toasts.show({
          title: t.readyToast(props.item.formTitle),
          description: t.rows(job.rows_count ?? 0),
          tone: 'success',
          action: { label: t.download[job.format], href: job.download_url },
        })
      } else {
        toasts.show({ title: t.failed, description: props.item.formTitle, tone: 'danger' })
      }
    }

    exportsStore.untrack(job.id)
  },
)
</script>

<template>
  <span hidden />
</template>
