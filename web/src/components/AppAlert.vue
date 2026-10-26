<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

/** Mensagem em bloco (erro do formulário, confirmação). Erros são anunciados na hora (role="alert"). */
const props = withDefaults(
  defineProps<{
    tone?: 'neutral' | 'success' | 'danger'
    title?: string
  }>(),
  { tone: 'neutral' },
)

const icons: Record<NonNullable<typeof props.tone>, IconName> = {
  neutral: 'info',
  success: 'circle-check',
  danger: 'circle-alert',
}

const role = computed(() => (props.tone === 'danger' ? 'alert' : 'status'))
</script>

<template>
  <div class="app-alert" :class="`app-alert--${props.tone}`" :role="role">
    <AppIcon :name="icons[props.tone]" class="app-alert__icon" />
    <div class="app-alert__body">
      <p v-if="props.title" class="app-alert__title">{{ props.title }}</p>
      <div class="app-alert__text"><slot /></div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.app-alert {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  padding: var(--space-3) var(--space-4);
  font-size: var(--font-size-sm);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-md);

  &--neutral {
    background: var(--color-surface-raised);
  }

  &--success {
    background: var(--color-success-soft);
  }

  &--danger {
    background: var(--color-danger-soft);
    border-color: var(--color-danger);
  }

  &__icon {
    flex-shrink: 0;
    font-size: var(--font-size-lg);
    color: var(--color-text-muted);
  }

  &--success &__icon {
    color: var(--color-success);
  }

  &--danger &__icon {
    color: var(--color-danger);
  }

  &__body {
    display: grid;
    gap: var(--space-1);
  }

  &__title {
    font-weight: var(--font-weight-semibold);
  }

  &__text {
    color: var(--color-text-muted);
  }
}
</style>
