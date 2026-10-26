<script setup lang="ts">
import AppCheckbox from '@/components/AppCheckbox.vue'
import type { MultipleChoiceField } from '@/types/schema'

import AnswerGroup from './AnswerGroup.vue'

/** Múltipla escolha: a ordem em que a pessoa marca é preservada (o servidor guarda assim). */
const props = defineProps<{
  field: MultipleChoiceField
  error?: string
}>()

const model = defineModel<string[]>({ default: () => [] })

function toggle(value: string, checked: boolean): void {
  const others = model.value.filter((item) => item !== value)
  model.value = checked ? [...others, value] : others
}
</script>

<template>
  <AnswerGroup
    :label="props.field.label"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
  >
    <div class="multiple-choice">
      <AppCheckbox
        v-for="option in props.field.options"
        :key="option.value"
        :model-value="model.includes(option.value)"
        :label="option.label"
        @update:model-value="(checked) => toggle(option.value, checked)"
      />
    </div>
  </AnswerGroup>
</template>

<style scoped lang="scss">
.multiple-choice {
  display: grid;
  gap: var(--space-3);
}
</style>
