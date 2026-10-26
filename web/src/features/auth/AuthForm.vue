<script setup lang="ts">
import { useTemplateRef } from 'vue'

import AppAlert from '@/components/AppAlert.vue'

/**
 * Formulário das telas de autenticação: validação nativa desligada (a nossa
 * mostra as mensagens), erro geral no topo e espaçamento padrão.
 */
defineProps<{
  error?: string
}>()

const emit = defineEmits<{
  submit: []
}>()

const root = useTemplateRef<HTMLFormElement>('root')

defineExpose({ el: root })
</script>

<template>
  <form ref="root" class="auth-form" novalidate @submit.prevent="emit('submit')">
    <AppAlert v-if="error" tone="danger">{{ error }}</AppAlert>
    <slot />
  </form>
</template>

<style scoped lang="scss">
.auth-form {
  display: grid;
  gap: var(--space-5);
}
</style>
