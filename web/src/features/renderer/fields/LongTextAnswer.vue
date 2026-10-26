<script setup lang="ts">
import AppTextarea from '@/components/AppTextarea.vue'
import type { LongTextField } from '@/types/schema'

const props = defineProps<{
  field: LongTextField
  error?: string
}>()

const model = defineModel<string>({ default: '' })
</script>

<template>
  <AppTextarea
    v-model="model"
    :label="props.field.label"
    :placeholder="props.field.placeholder ?? undefined"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
    :rows="5"
  />
</template>
