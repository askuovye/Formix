<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { useFieldIds } from '@/composables/useFieldIds'
import { common } from '@/strings'

/** Moldura de campos com vários controles (fieldset + legenda), com dica e erro ligados. */
const props = defineProps<{
  label: string
  hint?: string
  error?: string
  required?: boolean
}>()

const { hintId, errorId, describedBy } = useFieldIds(props)
</script>

<template>
  <fieldset
    class="answer-group"
    :aria-describedby="describedBy"
    :aria-invalid="props.error ? 'true' : undefined"
  >
    <legend class="answer-group__legend">
      {{ props.label }}
      <span v-if="props.required" class="answer-group__required">({{ common.required }})</span>
    </legend>
    <p v-if="props.hint" :id="hintId" class="answer-group__hint">{{ props.hint }}</p>

    <slot />

    <p v-if="props.error" :id="errorId" class="answer-group__error">
      <AppIcon name="circle-alert" />
      {{ props.error }}
    </p>
  </fieldset>
</template>

<style scoped lang="scss">
.answer-group {
  display: grid;
  gap: var(--space-2);
  min-width: 0;
  padding: 0;
  margin: 0;
  border: 0;

  &__legend {
    padding: 0;
    margin-block-end: var(--space-1);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__required {
    margin-inline-start: var(--space-1);
    font-weight: var(--font-weight-regular);
    color: var(--color-text-subtle);
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__error {
    display: flex;
    gap: var(--space-1);
    align-items: center;
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }
}
</style>
