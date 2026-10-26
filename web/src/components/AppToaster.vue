<script setup lang="ts">
import { AnimatePresence } from 'motion-v'

import { useToastStore } from '@/stores/useToastStore'
import { shell } from '@/strings'

import AppToastItem from './AppToastItem.vue'

/** Exibe os avisos da useToastStore. Montado uma única vez, no App.vue. */
const store = useToastStore()
</script>

<template>
  <section class="toaster" :aria-label="shell.toasts.region" aria-live="polite">
    <ol class="toaster__list">
      <AnimatePresence>
        <AppToastItem
          v-for="toast in store.toasts"
          :key="toast.id"
          :toast="toast"
          @dismiss="store.dismiss(toast.id)"
        />
      </AnimatePresence>
    </ol>
  </section>
</template>

<style scoped lang="scss">
.toaster {
  position: fixed;
  inset-block-end: 0;
  inset-inline-end: 0;
  z-index: var(--layer-tooltip);
  width: min(var(--dialog-width-md), 100%);
  padding: var(--space-4);
  pointer-events: none;

  &__list {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    list-style: none;
  }
}
</style>
