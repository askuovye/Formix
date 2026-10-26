<script setup lang="ts">
import { useFieldIds } from '@/composables/useFieldIds'

import AppFieldFrame from './AppFieldFrame.vue'
import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

const model = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    label: string
    type?: 'text' | 'email' | 'password' | 'search' | 'tel' | 'url' | 'date' | 'datetime-local'
    placeholder?: string
    hint?: string
    error?: string
    required?: boolean
    disabled?: boolean
    autocomplete?: string
    iconLeft?: IconName
    maxlength?: number
    inputmode?: 'text' | 'decimal' | 'numeric' | 'email' | 'tel'
  }>(),
  { type: 'text' },
)

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)
</script>

<template>
  <AppFieldFrame
    :label="props.label"
    :input-id="inputId"
    :hint-id="hintId"
    :error-id="errorId"
    :hint="props.hint"
    :error="props.error"
    :required="props.required"
  >
    <div class="text-field" :class="{ 'text-field--with-icon': props.iconLeft }">
      <AppIcon v-if="props.iconLeft" :name="props.iconLeft" class="text-field__icon" />
      <input
        :id="inputId"
        v-model="model"
        class="text-field__input"
        :type="props.type"
        :placeholder="props.placeholder"
        :required="props.required"
        :disabled="props.disabled"
        :autocomplete="props.autocomplete"
        :maxlength="props.maxlength"
        :inputmode="props.inputmode"
        :aria-invalid="props.error ? 'true' : undefined"
        :aria-describedby="describedBy"
      />
    </div>
  </AppFieldFrame>
</template>

<style scoped lang="scss">
.text-field {
  position: relative;

  &__input {
    @include control-surface;

    height: var(--control-height-md);
    padding-inline: var(--control-padding-x-sm);
    font-size: var(--font-size-sm);
  }

  &__icon {
    position: absolute;
    inset-block: 0;
    inset-inline-start: var(--space-3);
    margin-block: auto;
    color: var(--color-text-subtle);
    pointer-events: none;
  }

  &--with-icon &__input {
    padding-inline-start: var(--space-10);
  }
}
</style>
