<script setup lang="ts">
import AppButton from '@/components/AppButton.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import AppRadioGroup from '@/components/AppRadioGroup.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import type { MultipleChoiceField, SingleChoiceField } from '@/types/schema'

import type { ItemErrors } from '../schemaErrors'
import { ITEM_LIMITS } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'

const props = defineProps<{
  item: SingleChoiceField | MultipleChoiceField
  errors: ItemErrors
}>()

const store = useBuilderStore()
const t = builder.properties.options

type ChoiceItem = SingleChoiceField | MultipleChoiceField

function change(recipe: (item: ChoiceItem) => void, key: string): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.type === 'single_choice' || target.type === 'multiple_choice') recipe(target)
    },
    key,
  )
}

/**
 * Valor gravado nas respostas: gerado aqui (`opcao_3`) e nunca muda, mesmo
 * que o rótulo mude depois (padrão do contrato: ^[a-z0-9][a-z0-9_-]{0,59}$).
 */
function nextValue(): string {
  const taken = new Set(props.item.options.map((option) => option.value))

  for (let n = props.item.options.length + 1; ; n++) {
    if (!taken.has(`opcao_${n}`)) return `opcao_${n}`
  }
}

function add(): void {
  const value = nextValue()
  change((item) => item.options.push({ value, label: t.label(item.options.length + 1) }), 'options')
}

function rename(index: number, label: string): void {
  change((item) => {
    const option = item.options[index]
    if (option) option.label = label
  }, `options.${index}`)
}

function move(index: number, offset: number): void {
  change((item) => {
    const [option] = item.options.splice(index, 1)
    if (option) item.options.splice(index + offset, 0, option)
  }, 'options')
}

function remove(index: number): void {
  change((item) => item.options.splice(index, 1), 'options')
}

const displayOptions = [
  { value: 'radio', label: t.displayRadio },
  { value: 'select', label: t.displaySelect },
]

function setDisplay(value: string | undefined): void {
  change((item) => {
    if (item.type === 'single_choice' && (value === 'radio' || value === 'select')) {
      item.display = value
    }
  }, 'display')
}
</script>

<template>
  <div class="options-editor">
    <ol class="options-editor__list">
      <li
        v-for="(option, index) in props.item.options"
        :key="option.value"
        class="options-editor__row"
      >
        <AppTextField
          :model-value="option.label"
          :label="t.label(index + 1)"
          :maxlength="ITEM_LIMITS.options.labelMax"
          :error="props.errors[`options.${index}.label`]"
          class="options-editor__field"
          @update:model-value="(value) => rename(index, value)"
        />
        <div class="options-editor__actions">
          <AppIconButton
            icon="arrow-up"
            size="sm"
            :label="t.moveUp(option.label)"
            :disabled="index === 0"
            @click="move(index, -1)"
          />
          <AppIconButton
            icon="arrow-down"
            size="sm"
            :label="t.moveDown(option.label)"
            :disabled="index === props.item.options.length - 1"
            @click="move(index, 1)"
          />
          <AppIconButton
            icon="trash-2"
            size="sm"
            :label="t.remove(option.label)"
            :disabled="props.item.options.length <= ITEM_LIMITS.options.min"
            @click="remove(index)"
          />
        </div>
      </li>
    </ol>
    <p v-if="props.errors.options" class="options-editor__error" role="alert">
      {{ props.errors.options }}
    </p>
    <AppButton
      variant="secondary"
      size="sm"
      icon-left="plus"
      :disabled="props.item.options.length >= ITEM_LIMITS.options.max"
      @click="add"
    >
      {{ t.add }}
    </AppButton>

    <AppRadioGroup
      v-if="props.item.type === 'single_choice'"
      :model-value="props.item.display ?? 'radio'"
      :label="t.display"
      :options="displayOptions"
      @update:model-value="setDisplay"
    />
  </div>
</template>

<style scoped lang="scss">
.options-editor {
  display: grid;
  gap: var(--space-3);
  justify-items: start;

  &__list {
    display: grid;
    gap: var(--space-3);
    width: 100%;
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: var(--space-2);
    align-items: end;
  }

  &__actions {
    display: flex;
    min-height: var(--control-height-md);
    align-items: center;
  }

  &__error {
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }
}
</style>
