<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'

import AppFieldFrame from './AppFieldFrame.vue'

const model = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    label: string
    placeholder?: string
    hint?: string
    error?: string
    required?: boolean
    disabled?: boolean
    rows?: number
    maxlength?: number
  }>(),
  { rows: 4 },
)

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)
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
    <textarea
      :id="inputId"
      v-model="model"
      class="textarea"
      :rows="props.rows"
      :maxlength="props.maxlength"
      :placeholder="props.placeholder"
      :required="props.required"
      :disabled="props.disabled"
      :aria-invalid="props.error ? 'true' : undefined"
      :aria-describedby="describedBy"
    />
  </AppFieldFrame>
</template>

<style scoped lang="scss">
.textarea {
  @include control-surface;

  display: block;
  padding: var(--space-3);
  font-size: var(--font-size-sm);
  line-height: var(--line-height-base);
  resize: vertical;
}
</style>
