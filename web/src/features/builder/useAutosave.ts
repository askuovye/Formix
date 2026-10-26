import { onScopeDispose, ref, watch, type WatchSource } from 'vue'

import { ApiError } from '@/api/client'

export type SaveStatus = 'saved' | 'pending' | 'saving' | 'error' | 'invalid'

/** Espera depois da última mudança antes de salvar. */
export const AUTOSAVE_DELAY_MS = 1500

/**
 * Salvamento automático: observa `revision` e chama `save` depois de
 * AUTOSAVE_DELAY_MS sem mudanças. Nunca roda dois salvamentos ao mesmo tempo;
 * o que mudar durante um salvamento entra no próximo.
 */
export function useAutosave(revision: WatchSource<number>, save: () => Promise<unknown>) {
  const status = ref<SaveStatus>('saved')
  /** Erros de validação do último salvamento recusado (422), por caminho. */
  const fieldErrors = ref<Record<string, string[]>>({})

  let current = 0
  let savedRevision = 0
  let timer: ReturnType<typeof setTimeout> | undefined
  let inFlight: Promise<void> | null = null

  function schedule(): void {
    clearTimeout(timer)
    timer = setTimeout(() => void flush(), AUTOSAVE_DELAY_MS)
  }

  async function run(): Promise<void> {
    const target = current
    status.value = 'saving'

    try {
      await save()
      savedRevision = target
      fieldErrors.value = {}
      status.value = current === target ? 'saved' : 'pending'
    } catch (error) {
      const invalid = error instanceof ApiError && error.status === 422
      fieldErrors.value = invalid ? error.fieldErrors : {}
      status.value = invalid ? 'invalid' : 'error'
    }
  }

  /** Salva já o que estiver pendente (ao sair da página, por exemplo). */
  async function flush(): Promise<void> {
    clearTimeout(timer)

    if (inFlight) {
      await inFlight
    }

    if (current === savedRevision) return

    inFlight = run().finally(() => (inFlight = null))
    await inFlight

    // Mudou durante o salvamento: agenda o próximo.
    if (status.value === 'pending') {
      schedule()
    }
  }

  /** Recomeça do zero (formulário recém-carregado: nada a salvar). */
  function reset(revisionNow: number): void {
    clearTimeout(timer)
    current = revisionNow
    savedRevision = revisionNow
    fieldErrors.value = {}
    status.value = 'saved'
  }

  watch(revision, (value) => {
    current = value

    if (value === savedRevision) return

    status.value = 'pending'
    schedule()
  })

  onScopeDispose(() => clearTimeout(timer))

  const hasUnsaved = () => current !== savedRevision

  return { status, fieldErrors, flush, reset, hasUnsaved }
}
