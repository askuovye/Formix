<script setup lang="ts">
import { computed } from 'vue'

import AppNumberField from '@/components/AppNumberField.vue'
import AppSelect from '@/components/AppSelect.vue'
import AppSwitch from '@/components/AppSwitch.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import type { InputField, Visibility, VisibilityOperator } from '@/types/schema'

import type { ItemErrors } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'
import {
  VISIBILITY_LIMITS,
  conditionSources,
  defaultValue,
  defaultVisibility,
  defaultWhen,
  needsValue,
  operatorsFor,
  valueKindOf,
} from '../visibility'

/** Condição de mostrar/ocultar do campo selecionado. */
const props = defineProps<{
  item: InputField
  errors: ItemErrors
}>()

const store = useBuilderStore()
const t = builder.logic

const index = computed(() => store.items.findIndex((candidate) => candidate.id === props.item.id))
const sources = computed(() => conditionSources(store.items, index.value))
const visibility = computed(() => props.item.visibility ?? null)
const source = computed(() =>
  sources.value.find((candidate) => candidate.id === visibility.value?.when.field),
)

function change(recipe: (draft: Visibility) => void): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.kind === 'input' && target.visibility) recipe(target.visibility)
    },
    'visibility',
  )
}

function toggle(on: boolean): void {
  const first = sources.value[0]

  store.update(
    props.item.id,
    (target) => {
      if (target.kind !== 'input') return
      if (on && first) target.visibility = defaultVisibility(first)
      else delete target.visibility
    },
    'visibility',
  )
}

const actionOptions = [
  { value: 'show', label: t.actionOptions.show },
  { value: 'hide', label: t.actionOptions.hide },
]

// Origem apagada ou movida para depois: aparece na lista para o dono ver o
// que estava escolhido (o erro explica), mas ele escolhe outra.
const sourceOptions = computed(() =>
  sources.value.map((candidate) => ({
    value: candidate.id,
    label: candidate.label.trim() || t.unnamed,
  })),
)

const operatorOptions = computed(() =>
  (source.value ? operatorsFor(source.value) : []).map((operator) => ({
    value: operator,
    label: t.operators[operator],
  })),
)

const valueOptions = computed(() =>
  source.value && 'options' in source.value
    ? source.value.options.map((option) => ({ value: option.value, label: option.label }))
    : [],
)

const valueKind = computed(() => (source.value ? valueKindOf(source.value) : null))

function setSource(id: string | undefined): void {
  const next = sources.value.find((candidate) => candidate.id === id)
  if (next) change((draft) => (draft.when = defaultWhen(next)))
}

function setOperator(value: string | undefined): void {
  const current = source.value
  const operator = operatorsFor(current ?? props.item).find((candidate) => candidate === value)
  if (!current || !operator) return

  change((draft) => {
    const keep = needsValue(operator) && needsValue(draft.when.operator)
    const value = keep ? draft.when.value : defaultValue(current, operator)
    draft.when =
      value === undefined
        ? { field: draft.when.field, operator }
        : { field: draft.when.field, operator, value }
  })
}

function setValue(value: string | number | null | undefined): void {
  change((draft) => {
    if (value === null || value === undefined || value === '') delete draft.when.value
    else draft.when.value = value
  })
}

const operatorNeedsValue = computed(() =>
  visibility.value ? needsValue(visibility.value.when.operator as VisibilityOperator) : false,
)
</script>

<template>
  <div class="visibility-editor">
    <AppSwitch
      :model-value="visibility !== null"
      :label="t.toggle"
      :hint="sources.length > 0 || visibility ? t.toggleHint : t.noSources"
      :disabled="sources.length === 0 && !visibility"
      @update:model-value="toggle"
    />

    <template v-if="visibility">
      <p v-if="props.errors.visibility" class="visibility-editor__error" role="alert">
        {{ props.errors.visibility }}
      </p>

      <AppSelect
        :model-value="visibility.action"
        :label="t.action"
        :options="actionOptions"
        @update:model-value="
          (value) => change((draft) => (draft.action = value === 'hide' ? 'hide' : 'show'))
        "
      />
      <AppSelect
        :model-value="source ? visibility.when.field : undefined"
        :label="t.source"
        :options="sourceOptions"
        @update:model-value="setSource"
      />
      <AppSelect
        v-if="source"
        :model-value="visibility.when.operator"
        :label="t.operator"
        :options="operatorOptions"
        @update:model-value="setOperator"
      />

      <template v-if="source && operatorNeedsValue">
        <AppNumberField
          v-if="valueKind === 'number'"
          :model-value="typeof visibility.when.value === 'number' ? visibility.when.value : null"
          :label="t.value"
          @update:model-value="setValue"
        />
        <AppTextField
          v-else-if="valueKind === 'date'"
          type="date"
          :model-value="typeof visibility.when.value === 'string' ? visibility.when.value : ''"
          :label="t.value"
          @update:model-value="setValue"
        />
        <AppSelect
          v-else-if="valueKind === 'option'"
          :model-value="
            typeof visibility.when.value === 'string' ? visibility.when.value : undefined
          "
          :label="t.value"
          :options="valueOptions"
          @update:model-value="setValue"
        />
        <AppTextField
          v-else
          :model-value="typeof visibility.when.value === 'string' ? visibility.when.value : ''"
          :label="t.value"
          :placeholder="t.valuePlaceholder"
          :hint="t.textHint"
          :maxlength="VISIBILITY_LIMITS.textMax"
          @update:model-value="setValue"
        />
      </template>

      <p class="visibility-editor__note">{{ t.hiddenNote }}</p>
    </template>
  </div>
</template>

<style scoped lang="scss">
.visibility-editor {
  display: grid;
  gap: var(--space-3);

  &__error {
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }

  &__note {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }
}
</style>
