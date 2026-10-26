<script setup lang="ts">
import AppRadioGroup from '@/components/AppRadioGroup.vue'
import AppSelect from '@/components/AppSelect.vue'
import { renderer } from '@/strings'
import type { SingleChoiceField } from '@/types/schema'

/** Escolha única: botões de opção ou lista suspensa, como o dono escolheu. */
const props = defineProps<{
  field: SingleChoiceField
  error?: string
}>()

const model = defineModel<string | null>({ default: null })

function set(value: string | undefined): void {
  model.value = value ?? null
}
</script>

<template>
  <AppSelect
    v-if="props.field.display === 'select'"
    :model-value="model ?? undefined"
    :label="props.field.label"
    :options="props.field.options"
    :placeholder="renderer.choicePlaceholder"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
    @update:model-value="set"
  />
  <AppRadioGroup
    v-else
    :model-value="model ?? undefined"
    :label="props.field.label"
    :options="props.field.options"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
    @update:model-value="set"
  />
</template>
