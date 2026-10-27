<script setup lang="ts">
import { motion } from 'motion-v'
import { onBeforeUnmount, onMounted } from 'vue'

import { delay, slideUp } from '@/motion'
import type { Toast, ToastTone } from '@/stores/useToastStore'
import { common } from '@/strings'

import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

/** Um aviso: fecha sozinho após delay.toast, pausando com o mouse ou o foco em cima. */
const props = defineProps<{
  toast: Toast
}>()

const emit = defineEmits<{
  dismiss: []
}>()

const toneIcons: Record<ToastTone, IconName> = {
  neutral: 'info',
  success: 'circle-check',
  danger: 'circle-alert',
}

let timer: ReturnType<typeof setTimeout> | undefined
let remaining: number = delay.toast
let startedAt = 0

function start(): void {
  startedAt = Date.now()
  timer = setTimeout(() => emit('dismiss'), remaining)
}

function pause(): void {
  clearTimeout(timer)
  remaining -= Date.now() - startedAt
}

onMounted(start)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <motion.li
    class="toast"
    :class="`toast--${props.toast.tone}`"
    :role="props.toast.tone === 'danger' ? 'alert' : undefined"
    v-bind="slideUp"
    layout
    @mouseenter="pause"
    @mouseleave="start"
    @focusin="pause"
    @focusout="start"
  >
    <AppIcon :name="toneIcons[props.toast.tone]" class="toast__icon" />
    <div class="toast__text">
      <p class="toast__title">{{ props.toast.title }}</p>
      <p v-if="props.toast.description" class="toast__description">
        {{ props.toast.description }}
      </p>
      <a
        v-if="props.toast.action"
        :href="props.toast.action.href"
        class="toast__action"
        target="_blank"
        rel="noopener"
      >
        <AppIcon name="download" />
        {{ props.toast.action.label }}
      </a>
    </div>
    <button type="button" class="toast__close" :aria-label="common.close" @click="emit('dismiss')">
      <AppIcon name="x" />
    </button>
  </motion.li>
</template>

<style scoped lang="scss">
.toast {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  pointer-events: auto;
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);

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

  &__text {
    display: grid;
    flex: 1;
    gap: var(--space-1);
  }

  &__title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__description {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__action {
    display: inline-flex;
    gap: var(--space-1);
    align-items: center;
    justify-self: start;
    margin-block-start: var(--space-1);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text);
    text-decoration: underline;
    text-underline-offset: var(--space-1);

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__close {
    display: inline-grid;
    flex-shrink: 0;
    place-items: center;
    padding: var(--space-1);
    color: var(--color-text-muted);
    background: transparent;
    border: 0;
    border-radius: var(--radius-sm);
    cursor: pointer;

    &:hover {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }
  }
}
</style>
