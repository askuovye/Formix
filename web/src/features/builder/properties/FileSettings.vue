<script setup lang="ts">
import AppCheckbox from '@/components/AppCheckbox.vue'
import AppNumberField from '@/components/AppNumberField.vue'
import { builder } from '@/strings'
import type { FileAccept, FileField } from '@/types/schema'

import { ITEM_LIMITS, type ItemErrors } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'

const props = defineProps<{
  item: FileField
  errors: ItemErrors
}>()

const store = useBuilderStore()
const t = builder.properties.file

const categories: FileAccept[] = ['pdf', 'image', 'document', 'spreadsheet', 'text']

function change(recipe: (file: FileField['file']) => void, key: string): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.type === 'file') recipe(target.file)
    },
    key,
  )
}

function toggle(category: FileAccept, checked: boolean): void {
  change((file) => {
    const accept = file.accept.filter((value) => value !== category)
    // Mantém a ordem do catálogo, como o servidor normaliza.
    file.accept = checked
      ? categories.filter((value) => value === category || accept.includes(value))
      : accept
  }, 'file.accept')
}
</script>

<template>
  <div class="file-settings">
    <fieldset class="file-settings__accept">
      <legend class="file-settings__legend">{{ t.accept }}</legend>
      <AppCheckbox
        v-for="category in categories"
        :key="category"
        :model-value="props.item.file.accept.includes(category)"
        :label="t.categories[category]"
        @update:model-value="(checked) => toggle(category, checked)"
      />
      <p v-if="props.errors['file.accept']" class="file-settings__error" role="alert">
        {{ props.errors['file.accept'] }}
      </p>
    </fieldset>

    <AppNumberField
      :model-value="props.item.file.max_files"
      :label="t.maxFiles"
      :min="1"
      :max="ITEM_LIMITS.file.maxFiles"
      :error="props.errors['file.max_files']"
      @update:model-value="
        (value) => change((file) => (file.max_files = value ?? Number.NaN), 'file.max_files')
      "
    />
    <AppNumberField
      :model-value="props.item.file.max_size_mb"
      :label="t.maxSizeMb"
      :min="1"
      :max="ITEM_LIMITS.file.maxSizeMb"
      :error="props.errors['file.max_size_mb']"
      @update:model-value="
        (value) => change((file) => (file.max_size_mb = value ?? Number.NaN), 'file.max_size_mb')
      "
    />
  </div>
</template>

<style scoped lang="scss">
.file-settings {
  display: grid;
  gap: var(--space-4);

  &__accept {
    display: grid;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    border: 0;
  }

  &__legend {
    margin-block-end: var(--space-2);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__error {
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }
}
</style>
