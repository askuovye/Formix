<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'
import { computed, ref } from 'vue'

import { useFieldIds } from '@/composables/useFieldIds'
import { distance, scaleIn } from '@/motion'
import { common } from '@/strings'

import AppFieldFrame from './AppFieldFrame.vue'
import AppIcon from './AppIcon.vue'

export interface SelectOption {
  value: string
  label: string
  disabled?: boolean
}

const model = defineModel<string>()

const props = defineProps<{
  label: string
  options: readonly SelectOption[]
  placeholder?: string
  hint?: string
  error?: string
  required?: boolean
  disabled?: boolean
}>()

const { inputId, hintId, errorId, describedBy } = useFieldIds(props)

const open = ref(false)

// O texto exibido vem das opções: com a lista fechada (e desmontada para a
// animação de saída), o Reka não conhece os rótulos.
const selectedLabel = computed(
  () => props.options.find((option) => option.value === model.value)?.label,
)
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
    <SelectRoot
      v-model="model"
      v-model:open="open"
      :disabled="props.disabled"
      :required="props.required"
    >
      <SelectTrigger
        :id="inputId"
        class="select__trigger"
        :aria-invalid="props.error ? 'true' : undefined"
        :aria-describedby="describedBy"
      >
        <SelectValue
          class="select__value"
          :class="{ 'select__value--placeholder': !selectedLabel }"
        >
          {{ selectedLabel ?? props.placeholder ?? common.selectPlaceholder }}
        </SelectValue>
        <AppIcon name="chevron-down" class="select__chevron" />
      </SelectTrigger>

      <SelectPortal>
        <AnimatePresence>
          <SelectContent
            v-if="open"
            force-mount
            as-child
            position="popper"
            :side-offset="distance.sm"
          >
            <motion.div class="select__content" v-bind="scaleIn">
              <SelectViewport>
                <SelectItem
                  v-for="option in props.options"
                  :key="option.value"
                  :value="option.value"
                  :disabled="option.disabled"
                  class="select__item"
                >
                  <SelectItemText class="select__item-text">{{ option.label }}</SelectItemText>
                  <SelectItemIndicator class="select__indicator">
                    <AppIcon name="check" />
                  </SelectItemIndicator>
                </SelectItem>
              </SelectViewport>
            </motion.div>
          </SelectContent>
        </AnimatePresence>
      </SelectPortal>
    </SelectRoot>
  </AppFieldFrame>
</template>

<style scoped lang="scss">
.select {
  &__trigger {
    @include control-surface;

    display: flex;
    gap: var(--space-2);
    align-items: center;
    justify-content: space-between;
    height: var(--control-height-md);
    padding-inline: var(--control-padding-x-sm);
    font-size: var(--font-size-sm);
    text-align: start;
    cursor: pointer;
  }

  &__value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    &--placeholder {
      color: var(--color-text-subtle);
    }
  }

  &__chevron {
    color: var(--color-text-muted);
    transition: transform var(--duration-fast) var(--ease-out);
  }

  &__trigger[data-state='open'] &__chevron {
    transform: rotate(180deg);
  }

  &__content {
    @include popover-surface;

    z-index: var(--layer-popover);
    width: var(--reka-select-trigger-width);
    max-height: var(--reka-select-content-available-height);
    overflow-y: auto;
    transform-origin: var(--reka-select-content-transform-origin);
  }

  &__item {
    @include popover-item;

    justify-content: space-between;

    &[data-state='checked'] {
      font-weight: var(--font-weight-medium);
    }
  }

  &__indicator {
    display: inline-flex;
  }
}
</style>
