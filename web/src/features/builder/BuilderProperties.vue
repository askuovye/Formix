<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import AppAlert from '@/components/AppAlert.vue'
import AppSwitch from '@/components/AppSwitch.vue'
import AppTabs from '@/components/AppTabs.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import { SCHEMA_LIMITS, type InputField, type SchemaItem } from '@/types/schema'

import BuilderFormDetails from './BuilderFormDetails.vue'
import { paletteEntryFor } from './catalog'
import FileSettings from './properties/FileSettings.vue'
import OptionsEditor from './properties/OptionsEditor.vue'
import RangeSettings from './properties/RangeSettings.vue'
import TextValidation from './properties/TextValidation.vue'
import { isTextField } from './rules'
import { useBuilderStore } from './useBuilderStore'

const store = useBuilderStore()
const t = builder.properties

const item = computed(() => store.selected)

// Aba "Campo" (item selecionado) ou "Formulário" (dados gerais). Selecionar
// um item leva para a aba do campo.
const tab = ref<'field' | 'form'>(store.selectedId ? 'field' : 'form')
const tabs = [
  { value: 'field', label: builder.details.tabs.field },
  { value: 'form', label: builder.details.tabs.form },
]

watch(
  () => store.selectedId,
  (id) => {
    if (id) tab.value = 'field'
  },
)
const errors = computed(() => (item.value ? store.errorsFor(item.value.id) : {}))
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

/** Tipos com texto de exemplo (FieldType::acceptsPlaceholder no back-end). */
type PlaceholderField = Extract<
  InputField,
  { type: 'short_text' | 'long_text' | 'email' | 'number' }
>

function acceptsPlaceholder(i: InputField): i is PlaceholderField {
  return ['short_text', 'long_text', 'email', 'number'].includes(i.type)
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

const placeholder = field(
  '',
  (i) => (i.kind === 'input' && acceptsPlaceholder(i) ? (i.placeholder ?? '') : ''),
  (i, value: string) => {
    if (i.kind === 'input' && acceptsPlaceholder(i)) i.placeholder = value === '' ? null : value
  },
  'placeholder',
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

    <AppTabs
      :model-value="tab"
      :items="tabs"
      :label="builder.details.tabs.label"
      variant="segmented"
      class="properties__tabs"
      @update:model-value="(value) => (tab = value === 'form' ? 'form' : 'field')"
    >
      <template #field>
        <div class="properties__body">
          <p v-if="!item" class="properties__empty">{{ t.empty }}</p>

          <template v-else>
            <p class="properties__type">{{ typeName }}</p>
            <AppAlert v-if="errors.item" tone="danger">{{ errors.item }}</AppAlert>

            <template v-if="item.kind === 'input'">
              <AppTextarea
                v-model="label"
                :label="t.label"
                :rows="2"
                :maxlength="SCHEMA_LIMITS.labelMax"
                :error="errors.label"
                required
              />
              <AppTextarea
                v-model="helpText"
                :label="t.helpText"
                :rows="3"
                :maxlength="SCHEMA_LIMITS.helpTextMax"
                :error="errors.help_text"
              />
              <AppTextField
                v-if="acceptsPlaceholder(item)"
                v-model="placeholder"
                :label="t.placeholder"
                :hint="t.placeholderHint"
                :maxlength="SCHEMA_LIMITS.placeholderMax"
                :error="errors.placeholder"
              />
              <AppSwitch v-model="required" :label="t.required" :hint="t.requiredHint" />

              <section
                v-if="item.type === 'single_choice' || item.type === 'multiple_choice'"
                class="properties__section"
              >
                <h3 class="properties__heading">{{ t.sections.options }}</h3>
                <OptionsEditor :item="item" :errors="errors" />
              </section>

              <section v-else-if="item.type === 'file'" class="properties__section">
                <h3 class="properties__heading">{{ t.sections.file }}</h3>
                <FileSettings :item="item" :errors="errors" />
              </section>

              <section
                v-else-if="item.type === 'rating' || item.type === 'scale'"
                class="properties__section"
              >
                <h3 class="properties__heading">{{ t.sections.answer }}</h3>
                <RangeSettings :item="item" :errors="errors" />
              </section>

              <section v-else-if="isTextField(item)" class="properties__section">
                <h3 class="properties__heading">{{ t.sections.validation }}</h3>
                <TextValidation :item="item" :errors="errors" />
              </section>
            </template>

            <AppTextField
              v-else-if="item.type === 'section_title'"
              v-model="text"
              :label="t.sectionTitle"
              :maxlength="SCHEMA_LIMITS.sectionTitleMax"
              :error="errors.text"
              required
            />

            <AppTextarea
              v-else-if="item.type === 'text'"
              v-model="text"
              :label="t.text"
              :rows="5"
              :maxlength="SCHEMA_LIMITS.textBlockMax"
              :error="errors.text"
              required
            />

            <p v-else-if="item.type === 'page_break'" class="properties__empty">
              {{ t.pageBreakHint }}
            </p>

            <p v-else class="properties__empty">{{ t.noProperties }}</p>
          </template>
        </div>
      </template>
      <template #form>
        <BuilderFormDetails />
      </template>
    </AppTabs>
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

  &__body {
    display: grid;
    gap: var(--space-4);
  }

  &__type {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__empty {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__section {
    display: grid;
    gap: var(--space-3);
    padding-block-start: var(--space-4);
    border-block-start: var(--border-width) solid var(--color-border);
  }

  &__heading {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }
}
</style>
