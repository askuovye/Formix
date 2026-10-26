<script setup lang="ts">
import AppSwitch from '@/components/AppSwitch.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'

import { RULE_LIMITS } from '../rules'

/**
 * Regra anti-lixo liga/desliga. Ligada, mostra os parâmetros (slot) e o campo
 * de mensagem própria.
 */
const props = defineProps<{
  label: string
  hint?: string
  enabled: boolean
  message?: string
  messageError?: string
}>()

const emit = defineEmits<{
  toggle: [enabled: boolean]
  message: [message: string]
}>()

defineSlots<{ default?: () => unknown }>()

const t = builder.properties.rules
</script>

<template>
  <div class="rule-toggle" :class="{ 'rule-toggle--on': props.enabled }">
    <AppSwitch
      :model-value="props.enabled"
      :label="props.label"
      :hint="props.hint"
      @update:model-value="(value) => emit('toggle', value)"
    />
    <div v-if="props.enabled" class="rule-toggle__body">
      <slot />
      <AppTextField
        :model-value="props.message ?? ''"
        :label="t.message"
        :placeholder="t.messagePlaceholder"
        :maxlength="RULE_LIMITS.message"
        :error="props.messageError"
        @update:model-value="(value) => emit('message', value)"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.rule-toggle {
  display: grid;
  gap: var(--space-3);
  padding-block: var(--space-3);

  & + & {
    border-block-start: var(--border-width) solid var(--color-border);
  }

  &__body {
    display: grid;
    gap: var(--space-3);
    padding-inline-start: var(--space-3);
    border-inline-start: var(--border-width-strong) solid var(--color-border);
  }
}
</style>
