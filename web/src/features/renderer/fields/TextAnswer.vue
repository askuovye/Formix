<script setup lang="ts">
import { computed } from 'vue'

import AppTextField from '@/components/AppTextField.vue'
import type { DateField, EmailField, NumberField, ShortTextField } from '@/types/schema'

/** Texto curto, email, número e data: um input nativo com o tipo certo. */
const props = defineProps<{
  field: ShortTextField | EmailField | NumberField | DateField
  error?: string
}>()

const model = defineModel<string>({ default: '' })

const input = computed(() => {
  switch (props.field.type) {
    case 'email':
      return { type: 'email', inputmode: 'email', autocomplete: 'email' } as const
    // Texto com teclado numérico: aceita vírgula ("3,5"), que o input number recusaria.
    case 'number':
      return { type: 'text', inputmode: 'decimal', autocomplete: 'off' } as const
    case 'date':
      return { type: 'date', inputmode: undefined, autocomplete: 'off' } as const
    default:
      return { type: 'text', inputmode: undefined, autocomplete: undefined } as const
  }
})

const placeholder = computed(() =>
  'placeholder' in props.field ? (props.field.placeholder ?? undefined) : undefined,
)
</script>

<template>
  <AppTextField
    v-model="model"
    :label="props.field.label"
    :type="input.type"
    :inputmode="input.inputmode"
    :autocomplete="input.autocomplete"
    :placeholder="placeholder"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
  />
</template>
