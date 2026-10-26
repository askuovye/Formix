import { computed, reactive, ref, toValue, type MaybeRefOrGetter } from 'vue'

import { validateAnswers } from '@/features/validation/answers'
import { filesError } from '@/features/validation/files'
import { hiddenFieldIds } from '@/features/validation/visibility'
import type { FileField, InputField, SchemaItem } from '@/types/schema'

/** Etapas = trechos entre quebras de página (a quebra em si não aparece). Etapas vazias somem. */
export function splitSteps(items: readonly SchemaItem[]): SchemaItem[][] {
  const steps: SchemaItem[][] = [[]]

  for (const item of items) {
    if (item.type === 'page_break') steps.push([])
    else steps[steps.length - 1]?.push(item)
  }

  const filled = steps.filter((step) => step.length > 0)
  return filled.length > 0 ? filled : [[]]
}

/** Valor inicial de cada tipo, no formato que o componente do campo usa. */
export function emptyAnswer(field: InputField): unknown {
  switch (field.type) {
    case 'multiple_choice':
    case 'file':
      return []
    case 'single_choice':
    case 'rating':
    case 'scale':
      return null
    default:
      return ''
  }
}

/**
 * Resposta guardada no servidor → valor do componente do campo (edição).
 * Números de campos de texto voltam como texto; o resto já vem no formato certo.
 */
export function storedAnswer(field: InputField, value: unknown): unknown {
  if (value === null || value === undefined || field.type === 'file') return emptyAnswer(field)
  if (field.type === 'multiple_choice') return Array.isArray(value) ? value : []
  if (typeof value === 'number' && field.type !== 'rating' && field.type !== 'scale') {
    return String(value)
  }

  return value
}

export interface RendererOptions {
  /** Respostas já enviadas (edição). */
  initial?: Record<string, unknown>
  /** Quantos arquivos cada campo já tem guardados (edição). */
  storedFiles?: Record<string, number>
}

/** Respostas e anexos prontos para enviar. */
export interface RendererPayload {
  answers: Record<string, unknown>
  /** Arquivos por id de campo (só campos visíveis, com pelo menos um arquivo). */
  files: Record<string, File[]>
}

interface Validation {
  payload: RendererPayload
  errors: Record<string, string>
}

/**
 * Estado de quem responde: respostas, etapas, visibilidade e erros.
 *
 * Os erros aparecem depois da primeira tentativa de avançar ou enviar e, a
 * partir daí, acompanham o que a pessoa digita. O erro vindo do servidor some
 * quando o campo muda.
 */
export function useFormRenderer(
  source: MaybeRefOrGetter<readonly SchemaItem[]>,
  options: RendererOptions = {},
) {
  const items = computed(() => toValue(source))
  const inputs = computed(() =>
    items.value.filter((item): item is InputField => item.kind === 'input'),
  )

  const answers = reactive<Record<string, unknown>>(
    Object.fromEntries(
      inputs.value.map((field) => [
        field.id,
        options.initial && field.id in options.initial
          ? storedAnswer(field, options.initial[field.id])
          : emptyAnswer(field),
      ]),
    ),
  )

  const hidden = computed(() => new Set(hiddenFieldIds(items.value, answers)))
  const steps = computed(() => splitSteps(items.value))
  const stepIndex = ref(0)
  const isLastStep = computed(() => nextStepFrom(stepIndex.value) === null)

  /** Campos com erro à mostra (já houve tentativa); revalidados a cada mudança. */
  const checked = ref(new Set<string>())
  const clientErrors = ref<Record<string, string>>({})
  const serverErrors = ref<Record<string, string>>({})

  const errors = computed<Record<string, string>>(() => {
    const merged = { ...clientErrors.value, ...serverErrors.value }
    return Object.fromEntries(Object.entries(merged).filter(([id]) => !hidden.value.has(id)))
  })

  function isVisible(item: SchemaItem): boolean {
    return item.kind === 'block' || !hidden.value.has(item.id)
  }

  function visibleItems(index: number): SchemaItem[] {
    return (steps.value[index] ?? []).filter(isVisible)
  }

  /** Próxima (ou anterior) etapa com algo visível; `null` se não houver. */
  function nextStepFrom(index: number, direction: 1 | -1 = 1): number | null {
    for (let i = index + direction; i >= 0 && i < steps.value.length; i += direction) {
      if (visibleItems(i).some((item) => item.kind === 'input')) return i
    }
    return null
  }

  function filesOf(field: FileField): File[] {
    const value = answers[field.id]
    return Array.isArray(value) ? value.filter((item): item is File => item instanceof File) : []
  }

  /** Respostas (motor de validação) mais os anexos dos campos de arquivo visíveis. */
  function validate(): Validation {
    const result = validateAnswers(items.value, answers)
    const errors = { ...result.errors }
    const files: Record<string, File[]> = {}

    for (const field of inputs.value) {
      if (field.type !== 'file' || hidden.value.has(field.id)) continue
      const list = filesOf(field)
      const error = filesError(field, list, options.storedFiles?.[field.id] ?? 0)
      if (error) errors[field.id] = error
      else if (list.length > 0) files[field.id] = list
    }

    return { payload: { answers: result.answers, files }, errors }
  }

  function refreshClientErrors(result: Validation = validate()): void {
    clientErrors.value = Object.fromEntries(
      Object.entries(result.errors).filter(([id]) => checked.value.has(id)),
    )
  }

  function update(id: string, value: unknown): void {
    answers[id] = value

    if (id in serverErrors.value) {
      serverErrors.value = Object.fromEntries(
        Object.entries(serverErrors.value).filter(([key]) => key !== id),
      )
    }
    if (checked.value.size > 0) refreshClientErrors()
  }

  /** Valida os campos da etapa e passa a mostrar os erros deles; devolve se está tudo certo. */
  function checkStep(index: number): boolean {
    const ids = (steps.value[index] ?? [])
      .filter((item) => item.kind === 'input')
      .map((field) => field.id)
    ids.forEach((id) => checked.value.add(id))
    const result = validate()
    refreshClientErrors(result)

    return ids.every((id) => !(id in result.errors))
  }

  function next(): boolean {
    if (!checkStep(stepIndex.value)) return false

    const target = nextStepFrom(stepIndex.value)
    if (target !== null) stepIndex.value = target
    return true
  }

  function back(): void {
    stepIndex.value = nextStepFrom(stepIndex.value, -1) ?? 0
  }

  /** Etapa do primeiro campo com erro (na ordem do formulário). */
  function stepWithError(): number | null {
    const index = steps.value.findIndex((step) =>
      step.some((item) => item.kind === 'input' && item.id in errors.value),
    )
    return index < 0 ? null : index
  }

  /**
   * Respostas e anexos prontos para enviar ou `null` se houver erro (leva à
   * etapa dele). Campos ocultos vão como `null` (e sem arquivos): o servidor
   * descarta de todo jeito.
   */
  function payload(): RendererPayload | null {
    inputs.value.forEach((field) => checked.value.add(field.id))
    const result = validate()
    refreshClientErrors(result)

    if (Object.keys(errors.value).length > 0) {
      stepIndex.value = stepWithError() ?? stepIndex.value
      return null
    }

    return result.payload
  }

  /**
   * Erros 422 do servidor: os de campos visíveis vão para o campo (e a etapa
   * dele abre); devolve os que não pertencem a nenhum campo.
   */
  function setServerErrors(fieldErrors: Record<string, string>): string[] {
    const known = new Set(inputs.value.map((field) => field.id))
    const own = Object.entries(fieldErrors).filter(([id]) => known.has(id) && !hidden.value.has(id))

    serverErrors.value = Object.fromEntries(own)
    stepIndex.value = stepWithError() ?? stepIndex.value

    return Object.entries(fieldErrors)
      .filter(([id]) => !own.some(([ownId]) => ownId === id))
      .map(([, message]) => message)
  }

  return {
    answers,
    hidden,
    steps,
    stepIndex,
    isLastStep,
    errors,
    visibleItems,
    update,
    next,
    back,
    payload,
    setServerErrors,
  }
}

export type FormRenderer = ReturnType<typeof useFormRenderer>
