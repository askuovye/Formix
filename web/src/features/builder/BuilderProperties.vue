<script setup lang="ts">
import { computed } from 'vue'

import AppSwitch from '@/components/AppSwitch.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import { SCHEMA_LIMITS, type SchemaItem } from '@/types/schema'

import { paletteEntryFor } from './catalog'
import { useBuilderStore } from './useBuilderStore'

const store = useBuilderStore()
const t = builder.properties

const item = computed(() => store.selected)
const typeName = computed(() => (item.value ? builder.types[paletteEntryFor(item.value).key] : ''))

/** v-model de uma propriedade do item selecionado, gravando pelo store (desfazer). */
function field<T>(
  fallback: T,
  read: (item: SchemaItem) => T,
  write: (item: SchemaItem, value: T) => void,
  key: string,
) {
  return computed<T>({
    get: () => (item.value ? read(item.value) : fallback),
    set: (value) => {
      if (item.value) {
        store.update(item.value.id, (target) => write(target, value), key)
      }
    },
  })
}

const label = field(
  '',
  (i) => (i.kind === 'input' ? i.label : ''),
  (i, value: string) => {
    if (i.kind === 'input') i.label = value
  },
  'label',
)

const helpText = field(
  '',
  (i) => (i.kind === 'input' ? (i.help_text ?? '') : ''),
  (i, value: string) => {
    if (i.kind === 'input') i.help_text = value === '' ? null : value
  },
  'help_text',
)

const required = field(
  false,
  (i) => (i.kind === 'input' ? (i.required ?? false) : false),
  (i, value: boolean) => {
    if (i.kind === 'input') i.required = value
  },
  'required',
)

const text = field(
  '',
  (i) => (i.kind === 'block' && 'text' in i ? i.text : ''),
  (i, value: string) => {
    if (i.kind === 'block' && 'text' in i) i.text = value
  },
  'text',
)
</script>

<template>
  <div class="properties">
    <h2 class="properties__title">{{ t.title }}</h2>

    <p v-if="!item" class="properties__empty">{{ t.empty }}</p>

    <template v-else>
      <p class="properties__type">{{ typeName }}</p>

      <template v-if="item.kind === 'input'">
        <AppTextarea
          v-model="label"
          :label="t.label"
          :rows="2"
          :maxlength="SCHEMA_LIMITS.labelMax"
          required
        />
        <AppTextarea
          v-model="helpText"
          :label="t.helpText"
          :rows="3"
          :maxlength="SCHEMA_LIMITS.helpTextMax"
        />
        <AppSwitch v-model="required" :label="t.required" :hint="t.requiredHint" />
        <p class="properties__note">{{ t.more }}</p>
      </template>

      <AppTextField
        v-else-if="item.type === 'section_title'"
        v-model="text"
        :label="t.sectionTitle"
        required
      />

      <AppTextarea
        v-else-if="item.type === 'text'"
        v-model="text"
        :label="t.text"
        :rows="5"
        :maxlength="SCHEMA_LIMITS.textBlockMax"
        required
      />

      <p v-else class="properties__empty">{{ t.noProperties }}</p>
    </template>
  </div>
</template>

<style scoped lang="scss">
.properties {
  display: grid;
  gap: var(--space-4);
  align-content: start;

  &__title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
  }

  &__type {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__empty,
  &__note {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__note {
    padding: var(--space-3);
    font-size: var(--font-size-xs);
    background: var(--color-surface-raised);
    border-radius: var(--radius-md);
  }
}
</style>
