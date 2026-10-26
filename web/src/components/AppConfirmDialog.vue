<script setup lang="ts">
import AppButton from './AppButton.vue'
import AppDialog from './AppDialog.vue'

/**
 * Confirmação de uma ação (arquivar, excluir…). Fica aberta enquanto `loading`;
 * quem usa fecha pelo v-model:open quando a ação terminar.
 */
const props = withDefaults(
  defineProps<{
    title: string
    description: string
    confirmLabel: string
    cancelLabel: string
    tone?: 'primary' | 'danger'
    loading?: boolean
  }>(),
  { tone: 'primary' },
)

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  confirm: []
}>()
</script>

<template>
  <AppDialog v-model:open="open" :title="props.title" :description="props.description">
    <template #footer="{ close }">
      <AppButton variant="secondary" :disabled="props.loading" @click="close">
        {{ props.cancelLabel }}
      </AppButton>
      <AppButton :variant="props.tone" :loading="props.loading" @click="emit('confirm')">
        {{ props.confirmLabel }}
      </AppButton>
    </template>
  </AppDialog>
</template>
