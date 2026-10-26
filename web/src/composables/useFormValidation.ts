import * as v from 'valibot'
import { nextTick, ref, toValue, type MaybeRefOrGetter, type Ref } from 'vue'

import { toApiError } from '@/api/client'
import { common } from '@/strings'

export interface FormValidation<TOutput> {
  /** Primeira mensagem de erro de cada campo (cliente ou servidor). */
  errors: Ref<Record<string, string>>
  /** Erro geral do formulário (sem campo): rede, 429, mensagem do servidor. */
  formError: Ref<string | undefined>
  /** Valida no cliente; devolve os dados normalizados ou null (e preenche `errors`). */
  validate: () => TOutput | null
  /** Mapeia o erro da API: 422 vai para os campos, o resto para `formError`. */
  applyServerError: (error: unknown) => void
  /** Leva o foco ao primeiro campo com erro dentro do formulário. */
  focusFirstError: (form: HTMLElement | null | undefined) => Promise<void>
  reset: () => void
}

/**
 * Validação de formulário com Valibot (conveniência de UX; o back-end é a fonte
 * da verdade e seus erros 422 aparecem nos mesmos campos).
 */
export function useFormValidation<TSchema extends v.GenericSchema>(
  schema: TSchema,
  values: MaybeRefOrGetter<unknown>,
): FormValidation<v.InferOutput<TSchema>> {
  const errors = ref<Record<string, string>>({})
  const formError = ref<string>()

  function reset(): void {
    errors.value = {}
    formError.value = undefined
  }

  function validate(): v.InferOutput<TSchema> | null {
    reset()
    const result = v.safeParse(schema, toValue(values))

    if (result.success) {
      return result.output
    }

    const nested: Record<string, [string, ...string[]] | undefined> =
      v.flatten(result.issues).nested ?? {}
    errors.value = Object.fromEntries(
      Object.entries(nested).flatMap(([field, messages]) =>
        messages ? [[field, messages[0]]] : [],
      ),
    )

    return null
  }

  function applyServerError(error: unknown): void {
    reset()
    const apiError = toApiError(error)

    if (apiError.status === 422 && Object.keys(apiError.fieldErrors).length > 0) {
      errors.value = apiError.firstFieldErrors
      return
    }

    formError.value =
      apiError.status === 429 && apiError.retryAfter !== undefined
        ? common.tooManyAttempts(apiError.retryAfter)
        : apiError.message
  }

  async function focusFirstError(form: HTMLElement | null | undefined): Promise<void> {
    await nextTick()
    form?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
  }

  return { errors, formError, validate, applyServerError, focusFirstError, reset }
}
