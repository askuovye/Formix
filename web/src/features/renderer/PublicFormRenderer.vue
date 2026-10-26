<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { AnimatePresence, motion } from 'motion-v'
import { computed, nextTick, ref, useTemplateRef } from 'vue'

import { toApiError } from '@/api/client'
import { submitAnswers } from '@/api/publicForms'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import { fade, slideUp } from '@/motion'
import { renderer } from '@/strings'
import type { PublicForm, Receipt } from '@/types/api'
import type { SchemaItem } from '@/types/schema'

import FormItem from './FormItem.vue'
import { useFormRenderer } from './useFormRenderer'

/**
 * Formulário público montado a partir do schema publicado. Valida no cliente
 * (espelho do back-end), mas quem decide é o servidor: os erros 422 dele
 * voltam para os campos.
 */
const props = defineProps<{
  form: PublicForm
  items: SchemaItem[]
}>()

const emit = defineEmits<{
  submitted: [receipt: Receipt]
  /** O servidor deixou de aceitar respostas (409): a página recarrega a situação. */
  unavailable: []
}>()

const state = useFormRenderer(() => props.items)
const formElement = useTemplateRef<HTMLFormElement>('formElement')

const honeypot = ref('')
const formError = ref<string>()
// Mesma chave em retentativas: o servidor devolve o mesmo comprovante, sem duplicar.
const idempotencyKey = crypto.randomUUID()

const totalSteps = computed(() => state.steps.value.length)
const currentItems = computed(() => state.steps.value[state.stepIndex.value] ?? [])
const errorCount = computed(() => Object.keys(state.errors.value).length)

const mutation = useMutation({
  mutationFn: (answers: Record<string, unknown>) =>
    submitAnswers(props.form.slug, { answers, website: honeypot.value }, idempotencyKey),
  onSuccess: (receipt) => emit('submitted', receipt),
  onError: (error) => {
    const apiError = toApiError(error)

    if (apiError.status === 409) {
      emit('unavailable')
    } else if (apiError.status === 422) {
      const unmatched = state.setServerErrors(apiError.firstFieldErrors)
      formError.value = unmatched[0] ?? (errorCount.value > 0 ? undefined : renderer.genericError)
      void focusFirstError()
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
  const answers = state.payload()

  if (answers === null) {
    void focusFirstError()
    return
  }

  mutation.mutate(answers)
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
        icon-right="arrow-right"
        class="public-form__primary"
      >
        {{ renderer.next }}
      </AppButton>
      <AppButton
        v-else
        type="submit"
        icon-right="send"
        class="public-form__primary"
        :loading="mutation.isPending.value"
      >
        {{ mutation.isPending.value ? renderer.submitting : renderer.submit }}
      </AppButton>
    </div>

    <p v-if="state.isLastStep.value" class="public-form__privacy">
      {{ renderer.privacy }}
      <a
        class="public-form__link"
        :href="props.form.privacy_policy_url"
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
