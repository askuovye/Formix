<script setup lang="ts">
import { motion } from 'motion-v'

import AppIcon from '@/components/AppIcon.vue'
import type { IconName } from '@/components/icons/iconNames'
import { scaleIn } from '@/motion'

/** Tela de situação do formulário: enviado, indisponível, não encontrado. */
const props = withDefaults(
  defineProps<{
    icon: IconName
    title: string
    tone?: 'neutral' | 'success'
  }>(),
  { tone: 'neutral' },
)
</script>

<template>
  <motion.section
    class="form-status"
    :class="`form-status--${props.tone}`"
    role="status"
    v-bind="scaleIn"
  >
    <span class="form-status__icon">
      <AppIcon :name="props.icon" />
    </span>
    <h1 class="form-status__title">{{ props.title }}</h1>
    <slot />
  </motion.section>
</template>

<style scoped lang="scss">
.form-status {
  display: grid;
  gap: var(--space-4);
  justify-items: center;
  padding-block: var(--space-12);
  text-align: center;

  &__icon {
    display: grid;
    place-items: center;
    inline-size: var(--space-16);
    block-size: var(--space-16);
    font-size: var(--font-size-2xl);
    color: var(--color-text-muted);
    background: var(--color-surface-raised);
    border-radius: var(--radius-full);
  }

  &--success &__icon {
    color: var(--color-success);
    background: var(--color-success-soft);
  }

  &__title {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-semibold);
  }
}
</style>
