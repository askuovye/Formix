<script setup lang="ts">
import type { SchemaItem } from '@/types/schema'

import { fieldComponents } from './fields'

/** Um item do schema: bloco de conteúdo ou o componente do tipo do campo. */
const props = defineProps<{
  item: SchemaItem
  value?: unknown
  error?: string
}>()

const emit = defineEmits<{
  'update:value': [value: unknown]
}>()
</script>

<template>
  <template v-if="props.item.kind === 'block'">
    <h2 v-if="props.item.type === 'section_title'" class="form-item__section">
      {{ props.item.text }}
    </h2>
    <p v-else-if="props.item.type === 'text'" class="form-item__text">{{ props.item.text }}</p>
    <hr v-else-if="props.item.type === 'divider'" class="form-item__divider" />
  </template>

  <div v-else class="form-item" :data-field="props.item.id">
    <component
      :is="fieldComponents[props.item.type]"
      :field="props.item"
      :error="props.error"
      :model-value="props.value"
      @update:model-value="(value: unknown) => emit('update:value', value)"
    />
  </div>
</template>

<style scoped lang="scss">
.form-item {
  min-width: 0;

  &__section {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
  }

  &__text {
    color: var(--color-text-muted);
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  &__divider {
    width: 100%;
    margin: 0;
    border: 0;
    border-block-start: var(--border-width) solid var(--color-border);
  }
}
</style>
