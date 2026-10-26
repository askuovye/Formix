<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'

import AppFieldFrame from './AppFieldFrame.vue'

/** Número inteiro ou decimal. Campo vazio vale `null`. */
const model = defineModel<number | null>({ default: null })

const props = withDefaults(
  defineProps<{
    label: string
    hint?: string
    error?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
    min?: number
    max?: number
    step?: number
  }>(),
  { step: 1 },
)

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)

function onInput(event: Event): void {
  const raw = (event.target as HTMLInputElement).value
  const value = raw === '' ? null : Number(raw)

  model.value = value === null || Number.isNaN(value) ? null : value
}
</script>

<template>
  <AppFieldFrame
    :label="props.label"
    :input-id="inputId"
    :hint-id="hintId"
    :error-id="errorId"
    :hint="props.hint"
    :error="props.error"
    :required="props.required"
  >
    <input
      :id="inputId"
      :value="model === null || Number.isNaN(model) ? '' : model"
      class="number-field"
      type="number"
      inputmode="decimal"
      :min="props.min"
      :max="props.max"
      :step="props.step"
      :placeholder="props.placeholder"
      :required="props.required"
      :disabled="props.disabled"
      :aria-invalid="props.error ? 'true' : undefined"
      :aria-describedby="describedBy"
      @input="onInput"
    />
  </AppFieldFrame>
</template>

<style scoped lang="scss">
.number-field {
  @include control-surface;

  height: var(--control-height-md);
  padding-inline: var(--control-padding-x-sm);
  font-size: var(--font-size-sm);
}
</style>
