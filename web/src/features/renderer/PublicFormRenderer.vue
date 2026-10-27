<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { AnimatePresence, motion } from 'motion-v'
import { computed, nextTick, ref, useTemplateRef } from 'vue'

import { toApiError } from '@/api/client'
import { submitAnswers, updateSubmission } from '@/api/publicForms'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import { MAX_TOTAL_UPLOAD_MB, totalTooLarge } from '@/features/validation/files'
import { fade, slideUp } from '@/motion'
import { renderer, validation } from '@/strings'
import type { EditableSubmission, Receipt, StoredFile } from '@/types/api'
import type { FormTheme, SchemaItem } from '@/types/schema'

import FormItem from './FormItem.vue'
import { useFormRenderer, type RendererPayload } from './useFormRenderer'

/**
 * Formulário montado a partir do schema. Valida no cliente (espelho do
 * back-end), mas quem decide é o servidor: os erros 422 dele voltam para os
 * campos. Na pré-visualização do builder nada é enviado.
 */
const props = defineProps<{
  items: SchemaItem[]
  /** Formulário que recebe a resposta (ignorado na pré-visualização). */
  slug?: string
  privacyUrl?: string
  buttonStyle?: FormTheme['button_style']
  /** Pré-visualização: valida tudo, mas emite `previewed` em vez de enviar. */
  preview?: boolean
  /** Edição pelo link secreto: salva a resposta existente em vez de criar outra. */
  editing?: { submissionId: string; token: string }
  /** Respostas já enviadas (edição). */
  initial?: Record<string, unknown>
  /** Anexos já guardados por campo (edição). */
  storedFiles?: Record<string, StoredFile[]>
}>()

const emit = defineEmits<{
  submitted: [receipt: Receipt]
  /** Edição salva: a resposta como ficou. */
  updated: [submission: EditableSubmission]
  /**
   * O servidor recusou por situação, não por dados (409; na edição também 404
   * e 410): a página recarrega ou mostra o motivo.
   */
  unavailable: [status: number]
  /** Pré-visualização chegou ao fim com tudo válido. */
  previewed: []
}>()

const state = useFormRenderer(() => props.items, {
  initial: props.initial,
  storedFiles: Object.fromEntries(
    Object.entries(props.storedFiles ?? {}).map(([id, files]) => [id, files.length]),
  ),
})
const formElement = useTemplateRef<HTMLFormElement>('formElement')

const honeypot = ref('')
const formError = ref<string>()
// Mesma chave em retentativas: o servidor devolve o mesmo comprovante, sem duplicar.
const idempotencyKey = crypto.randomUUID()
// Tempo de preenchimento (tempo médio nos resultados); o servidor descarta valores absurdos.
const openedAt = performance.now()

const totalSteps = computed(() => state.steps.value.length)
const currentItems = computed(() => state.steps.value[state.stepIndex.value] ?? [])
const errorCount = computed(() => Object.keys(state.errors.value).length)
const primaryVariant = computed(() => (props.buttonStyle === 'outline' ? 'outline' : 'primary'))

/** Fração já enviada dos anexos (0 a 1); `null` sem anexos. */
const progress = ref<number | null>(null)
const submitLabel = computed(() => {
  if (!mutation.isPending.value) return props.editing ? renderer.edit.save : renderer.submit
  return progress.value === null
    ? renderer.submitting
    : renderer.submittingProgress(Math.round(progress.value * 100))
})

const mutation = useMutation({
  mutationFn: ({ answers, files }: RendererPayload): Promise<Receipt | EditableSubmission> => {
    progress.value = Object.keys(files).length > 0 ? 0 : null
    const onProgress = (fraction: number) => (progress.value = fraction)

    if (props.editing) {
      const { submissionId, token } = props.editing
      return updateSubmission(props.slug ?? '', submissionId, token, { answers, files }, onProgress)
    }
    return submitAnswers(
      props.slug ?? '',
      {
        answers,
        files,
        website: honeypot.value,
        durationSeconds: Math.round((performance.now() - openedAt) / 1000),
      },
      idempotencyKey,
      onProgress,
    )
  },
  onSuccess: (result) => {
    if ('success_message' in result) emit('submitted', result)
    else emit('updated', result)
  },
  onError: (error) => {
    const apiError = toApiError(error)

    const situation = props.editing ? [404, 409, 410] : [409]
    if (situation.includes(apiError.status)) {
      emit('unavailable', apiError.status)
    } else if (apiError.status === 422) {
      const unmatched = state.setServerErrors(apiError.firstFieldErrors)
      formError.value = unmatched[0] ?? (errorCount.value > 0 ? undefined : renderer.genericError)
      void focusFirstError()
    } else if (apiError.status === 413) {
      formError.value = validation.files.totalTooLarge(MAX_TOTAL_UPLOAD_MB)
    } else if (apiError.status === 429 && apiError.retryAfter !== undefined) {
      formError.value = renderer.tooManyAttempts(apiError.retryAfter)
    } else {
      formError.value = apiError.message
    }
  },
})

/** Foco no primeiro controle do primeiro campo com erro, na ordem da tela. */
async function focusFirstError(): Promise<void> {
  await nextTick()
  const ids = Object.keys(state.errors.value)
  const fields = formElement.value?.querySelectorAll<HTMLElement>('[data-field]') ?? []
  const first = [...fields].find((element) => ids.includes(element.dataset.field ?? ''))

  first?.querySelector<HTMLElement>('input, textarea, button')?.focus()
}

async function focusStart(): Promise<void> {
  await nextTick()
  formElement.value?.querySelector<HTMLElement>('input, textarea, button')?.focus()
}

function onNext(): void {
  formError.value = undefined
  if (state.next()) void focusStart()
  else void focusFirstError()
}

function onBack(): void {
  formError.value = undefined
  state.back()
  void focusStart()
}

function onSubmit(): void {
  if (mutation.isPending.value) return
  if (!state.isLastStep.value) return onNext()

  formError.value = undefined
  const payload = state.payload()

  if (payload === null) {
    void focusFirstError()
    return
  }
  // A soma vale para o envio inteiro: avisa antes de subir megabytes à toa.
  if (totalTooLarge(Object.values(payload.files).flat())) {
    formError.value = validation.files.totalTooLarge(MAX_TOTAL_UPLOAD_MB)
    return
  }

  if (props.preview) emit('previewed')
  else mutation.mutate(payload)
}
</script>

<template>
  <form ref="formElement" class="public-form" novalidate @submit.prevent="onSubmit">
    <p v-if="totalSteps > 1" class="public-form__progress">
      <span>{{ renderer.step(state.stepIndex.value + 1, totalSteps) }}</span>
      <span class="public-form__bar" aria-hidden="true">
        <span
          class="public-form__bar-fill"
          :style="{ inlineSize: `${((state.stepIndex.value + 1) / totalSteps) * 100}%` }"
        />
      </span>
    </p>

    <AnimatePresence mode="wait" :initial="false">
      <motion.div :key="state.stepIndex.value" class="public-form__step" v-bind="fade">
        <template v-for="item in currentItems" :key="item.id">
          <AnimatePresence :initial="false">
            <motion.div
              v-if="item.kind === 'block' || !state.hidden.value.has(item.id)"
              class="public-form__item"
              v-bind="slideUp"
            >
              <FormItem
                :item="item"
                :value="state.answers[item.id]"
                :error="state.errors.value[item.id]"
                :stored="item.kind === 'input' ? props.storedFiles?.[item.id] : undefined"
                @update:value="(value) => state.update(item.id, value)"
              />
            </motion.div>
          </AnimatePresence>
        </template>
      </motion.div>
    </AnimatePresence>

    <!-- Honeypot: invisível para pessoas; robôs que preenchem tudo são descartados pelo servidor. -->
    <div class="public-form__trap" aria-hidden="true">
      <label for="website">{{ renderer.honeypot }}</label>
      <input id="website" v-model="honeypot" name="website" tabindex="-1" autocomplete="off" />
    </div>

    <AppAlert v-if="formError" tone="danger">{{ formError }}</AppAlert>
    <p v-else-if="errorCount > 0" class="public-form__summary" role="status">
      {{ renderer.fixErrors(errorCount) }}
    </p>

    <div class="public-form__actions">
      <AppButton
        v-if="state.stepIndex.value > 0"
        variant="secondary"
        icon-left="arrow-left"
        @click="onBack"
      >
        {{ renderer.back }}
      </AppButton>
      <AppButton
        v-if="!state.isLastStep.value"
        type="submit"
        :variant="primaryVariant"
        icon-right="arrow-right"
        class="public-form__primary"
      >
        {{ renderer.next }}
      </AppButton>
      <AppButton
        v-else
        type="submit"
        :variant="primaryVariant"
        icon-right="send"
        class="public-form__primary"
        :loading="mutation.isPending.value"
      >
        {{ submitLabel }}
      </AppButton>
    </div>

    <p v-if="state.isLastStep.value && props.privacyUrl" class="public-form__privacy">
      {{ renderer.privacy }}
      <a
        class="public-form__link"
        :href="props.privacyUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ renderer.privacyLink }}</a
      >.
    </p>
  </form>
</template>

<style scoped lang="scss">
.public-form {
  display: grid;
  gap: var(--space-6);

  &__progress {
    display: grid;
    gap: var(--space-2);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__bar {
    display: block;
    block-size: var(--space-1);
    overflow: hidden;
    background: var(--color-border);
    border-radius: var(--radius-full);
  }

  &__bar-fill {
    display: block;
    block-size: 100%;
    background: var(--color-primary);
    border-radius: inherit;
    transition: inline-size var(--duration-slow) var(--ease-out);
  }

  &__step {
    display: grid;
    gap: var(--space-6);
  }

  &__trap {
    @include visually-hidden;
  }

  &__summary {
    font-size: var(--font-size-sm);
    color: var(--color-danger);
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
  }

  &__primary {
    margin-inline-start: auto;
  }

  &__privacy {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__link {
    color: inherit;
  }
}
</style>
