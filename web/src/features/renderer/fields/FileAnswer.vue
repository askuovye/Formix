<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { renderer } from '@/strings'
import type { FileField } from '@/types/schema'

import AnswerGroup from './AnswerGroup.vue'

/** Os anexos chegam na parte 4d (Uppy); por enquanto o campo só avisa. */
const props = defineProps<{
  field: FileField
  error?: string
}>()
</script>

<template>
  <AnswerGroup
    :label="props.field.label"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
  >
    <p class="file-answer">
      <AppIcon name="upload" />
      {{ renderer.fileSoon }}
    </p>
  </AnswerGroup>
</template>

<style scoped lang="scss">
.file-answer {
  display: flex;
  gap: var(--space-2);
  align-items: center;
  justify-content: center;
  padding: var(--space-6);
  font-size: var(--font-size-sm);
  color: var(--color-text-subtle);
  border: var(--border-width-strong) dashed var(--color-border-strong);
  border-radius: var(--radius-md);
}
</style>
