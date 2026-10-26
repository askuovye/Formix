<script setup lang="ts">
import AppNumberField from '@/components/AppNumberField.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import type { RatingField, ScaleField } from '@/types/schema'

import { ITEM_LIMITS, type ItemErrors } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'

/** Estrelas (quantidade) e escala (início, fim e rótulos das pontas). */
const props = defineProps<{
  item: RatingField | ScaleField
  errors: ItemErrors
}>()

const store = useBuilderStore()
const t = builder.properties

function setStars(value: number | null): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.type === 'rating') target.rating.max = value ?? Number.NaN
    },
    'rating.max',
  )
}

function setScale<K extends keyof ScaleField['scale']>(
  key: K,
  value: ScaleField['scale'][K],
): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.type === 'scale') target.scale[key] = value
    },
    `scale.${key}`,
  )
}
</script>

<template>
  <AppNumberField
    v-if="props.item.type === 'rating'"
    :model-value="props.item.rating.max"
    :label="t.rating.max"
    :min="ITEM_LIMITS.rating.min"
    :max="ITEM_LIMITS.rating.max"
    :error="props.errors['rating.max']"
    @update:model-value="setStars"
  />

  <div v-else class="range-settings">
    <div class="range-settings__pair">
      <AppNumberField
        :model-value="props.item.scale.min"
        :label="t.scale.min"
        :min="ITEM_LIMITS.scale.lowest"
        :max="ITEM_LIMITS.scale.highest - 1"
        :error="props.errors['scale.min']"
        @update:model-value="(value) => setScale('min', value ?? Number.NaN)"
      />
      <AppNumberField
        :model-value="props.item.scale.max"
        :label="t.scale.max"
        :min="ITEM_LIMITS.scale.lowest + 1"
        :max="ITEM_LIMITS.scale.highest"
        :error="props.errors['scale.max']"
        @update:model-value="(value) => setScale('max', value ?? Number.NaN)"
      />
    </div>
    <AppTextField
      :model-value="props.item.scale.min_label ?? ''"
      :label="t.scale.minLabel"
      :placeholder="t.scale.labelPlaceholderMin"
      :maxlength="ITEM_LIMITS.scale.labelMax"
      :error="props.errors['scale.min_label']"
      @update:model-value="(value) => setScale('min_label', value === '' ? null : value)"
    />
    <AppTextField
      :model-value="props.item.scale.max_label ?? ''"
      :label="t.scale.maxLabel"
      :placeholder="t.scale.labelPlaceholderMax"
      :maxlength="ITEM_LIMITS.scale.labelMax"
      :error="props.errors['scale.max_label']"
      @update:model-value="(value) => setScale('max_label', value === '' ? null : value)"
    />
  </div>
</template>

<style scoped lang="scss">
.range-settings {
  display: grid;
  gap: var(--space-4);

  &__pair {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-3);
  }
}
</style>
