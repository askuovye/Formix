<script setup lang="ts">
import { ref, watch } from 'vue'

import { useFieldIds } from '@/composables/useFieldIds'
import { common } from '@/strings'

import AppFieldFrame from './AppFieldFrame.vue'

/**
 * Cor em "#RRGGBB": amostra com o seletor nativo do sistema e o código ao lado.
 * O código digitado só vale quando completo; ao sair do campo, volta ao valor atual.
 */
const model = defineModel<string>({ required: true })

const props = defineProps<{
  label: string
  hint?: string
  error?: string
  disabled?: boolean
}>()

const HEX = /^#?[0-9a-fA-F]{6}$/

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)
const text = ref(model.value.toUpperCase())

watch(model, (value) => {
  if (value.toUpperCase() !== normalized(text.value)) text.value = value.toUpperCase()
})

function normalized(value: string): string {
  const trimmed = value.trim()
  return `#${trimmed.replace(/^#/, '')}`.toUpperCase()
}

function onText(event: Event): void {
  text.value = (event.target as HTMLInputElement).value
  if (HEX.test(text.value.trim())) model.value = normalized(text.value)
}

function onPick(event: Event): void {
  model.value = (event.target as HTMLInputElement).value.toUpperCase()
}
</script>

<template>
  <AppFieldFrame
    :label="props.label"
    :input-id="inputId"
    :hint-id="hintId"
    :error-id="errorId"
    :hint="props.hint"
    :error="props.error"
  >
    <div class="color-field">
      <input
        type="color"
        class="color-field__swatch"
        :value="model"
        :disabled="props.disabled"
        :aria-label="common.pickColor(props.label)"
        @input="onPick"
      />
      <input
        :id="inputId"
        class="color-field__code"
        :value="text"
        :disabled="props.disabled"
        maxlength="7"
        spellcheck="false"
        autocomplete="off"
        :aria-invalid="props.error ? 'true' : undefined"
        :aria-describedby="describedBy"
        @input="onText"
        @blur="text = model.toUpperCase()"
      />
    </div>
  </AppFieldFrame>
</template>

<style scoped lang="scss">
.color-field {
  display: flex;
  gap: var(--space-2);
  align-items: center;

  &__swatch {
    flex-shrink: 0;
    inline-size: var(--control-height-md);
    block-size: var(--control-height-md);
    padding: var(--space-1);
    cursor: pointer;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);

    &:focus-visible {
      @include focus-ring;
    }

    &:disabled {
      cursor: not-allowed;
      opacity: var(--opacity-disabled);
    }
  }

  &__code {
    @include control-surface;

    block-size: var(--control-height-md);
    padding-inline: var(--control-padding-x-sm);
    font-size: var(--font-size-sm);
    text-transform: uppercase;
  }
}
</style>
