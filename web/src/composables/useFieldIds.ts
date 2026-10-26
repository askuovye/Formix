import { computed, useId, type ComputedRef } from 'vue'

interface FieldMessages {
  hint?: string
  error?: string
}

export interface FieldIds {
  inputId: string
  hintId: string
  errorId: string
  /** Valor de aria-describedby: dica e erro presentes, nessa ordem. */
  describedBy: ComputedRef<string | undefined>
}

/** Ids estáveis (SSR-safe) para ligar label, dica e erro ao controle. */
export function useFieldIds(messages: FieldMessages): FieldIds {
  const base = useId()
  const hintId = `${base}-hint`
  const errorId = `${base}-error`

  const describedBy = computed(() => {
    const ids = [messages.hint ? hintId : null, messages.error ? errorId : null].filter(Boolean)

    return ids.length > 0 ? ids.join(' ') : undefined
  })

  return { inputId: `${base}-input`, hintId, errorId, describedBy }
}
