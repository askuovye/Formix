import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'

import { ApiError } from '@/api/client'

import { AUTOSAVE_DELAY_MS, InvalidDraftError, useAutosave } from '../useAutosave'

function setup(save: () => Promise<unknown>) {
  const revision = ref(0)
  const scope = effectScope()
  const autosave = scope.run(() => useAutosave(revision, save))

  if (!autosave) throw new Error('escopo')

  return { revision, autosave, scope }
}

/** Promessa controlada pelo teste. */
function deferred() {
  let resolve!: () => void
  let reject!: (error: unknown) => void
  const promise = new Promise<void>((res, rej) => {
    resolve = res
    reject = rej
  })

  return { promise, resolve, reject }
}

describe('useAutosave', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('espera o intervalo sem mudanças e salva uma vez só', async () => {
    const save = vi.fn().mockResolvedValue(undefined)
    const { revision, autosave } = setup(save)

    revision.value = 1
    await nextTick()
    expect(autosave.status.value).toBe('pending')

    vi.advanceTimersByTime(AUTOSAVE_DELAY_MS - 100)
    revision.value = 2
    await nextTick()
    vi.advanceTimersByTime(AUTOSAVE_DELAY_MS - 100)
    expect(save).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(100)

    expect(save).toHaveBeenCalledOnce()
    expect(autosave.status.value).toBe('saved')
    expect(autosave.hasUnsaved()).toBe(false)
  })

  it('mudança durante um salvamento entra no próximo, sem salvar em paralelo', async () => {
    const first = deferred()
    const save = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue(undefined)
    const { revision, autosave } = setup(save)

    revision.value = 1
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    expect(autosave.status.value).toBe('saving')

    revision.value = 2
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    expect(save).toHaveBeenCalledOnce()

    first.resolve()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)

    expect(save).toHaveBeenCalledTimes(2)
    expect(autosave.status.value).toBe('saved')
  })

  it('422 marca como inválido e guarda os erros; outros erros pedem nova tentativa', async () => {
    const save = vi
      .fn()
      .mockRejectedValueOnce(
        new ApiError('Dados inválidos.', 422, { 'schema.fields.0.label': ['Obrigatório.'] }),
      )
      .mockRejectedValueOnce(new ApiError('Sem conexão.', 0))
      .mockResolvedValue(undefined)
    const { revision, autosave } = setup(save)

    revision.value = 1
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    expect(autosave.status.value).toBe('invalid')
    expect(autosave.fieldErrors.value).toEqual({ 'schema.fields.0.label': ['Obrigatório.'] })

    revision.value = 2
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    expect(autosave.status.value).toBe('error')
    expect(autosave.hasUnsaved()).toBe(true)

    await autosave.flush()
    expect(autosave.status.value).toBe('saved')
  })

  it('rascunho inválido no próprio builder não vai ao servidor e fica como inválido', async () => {
    const save = vi.fn().mockRejectedValue(new InvalidDraftError())
    const { revision, autosave } = setup(save)

    revision.value = 1
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)

    expect(autosave.status.value).toBe('invalid')
    expect(autosave.fieldErrors.value).toEqual({})
    expect(autosave.hasUnsaved()).toBe(true)
  })

  it('flush salva na hora o que estiver pendente', async () => {
    const save = vi.fn().mockResolvedValue(undefined)
    const { revision, autosave } = setup(save)

    revision.value = 1
    await nextTick()
    await autosave.flush()

    expect(save).toHaveBeenCalledOnce()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    expect(save).toHaveBeenCalledOnce()
  })

  it('reset (formulário recém-carregado) não salva nada', async () => {
    const save = vi.fn().mockResolvedValue(undefined)
    const { revision, autosave } = setup(save)

    revision.value = 5
    autosave.reset(5)
    await nextTick()
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)

    expect(save).not.toHaveBeenCalled()
    expect(autosave.status.value).toBe('saved')
  })
})
