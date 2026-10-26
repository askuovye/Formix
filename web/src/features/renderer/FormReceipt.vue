<script setup lang="ts">
import { computed } from 'vue'

import AppButton from '@/components/AppButton.vue'
import { renderer } from '@/strings'
import type { Receipt } from '@/types/api'

import FormStatusPanel from './FormStatusPanel.vue'

/** Confirmação de envio, com o link de edição quando o dono permite. */
const props = defineProps<{
  receipt: Receipt
}>()

const formatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

const editableUntil = computed(() =>
  props.receipt.editable_until ? formatter.format(new Date(props.receipt.editable_until)) : null,
)
</script>

<template>
  <FormStatusPanel icon="circle-check" :title="renderer.receipt.title" tone="success">
    <p class="receipt__message">{{ props.receipt.success_message }}</p>

    <template v-if="props.receipt.edit_url">
      <AppButton variant="secondary" icon-left="pencil" :href="props.receipt.edit_url">
        {{ renderer.receipt.edit }}
      </AppButton>
      <p v-if="editableUntil" class="receipt__hint">
        {{ renderer.receipt.editHint(editableUntil) }}
      </p>
    </template>
  </FormStatusPanel>
</template>

<style scoped lang="scss">
.receipt {
  &__message {
    max-inline-size: var(--dialog-width-md);
    color: var(--color-text-muted);
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  &__hint {
    max-inline-size: var(--dialog-width-md);
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }
}
</style>
