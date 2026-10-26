<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'

import { fade, slideInEnd, slideInStart } from '@/motion'
import { common } from '@/strings'

import AppIconButton from './AppIconButton.vue'

/** Painel lateral modal (menu do painel e colunas do builder no celular). Abre por v-model:open. */
const props = withDefaults(
  defineProps<{
    /** Nome acessível do painel (fica visualmente oculto). */
    title: string
    /** Borda de onde a gaveta entra. */
    side?: 'start' | 'end'
    /** `nav`: estreita, para menus; `panel`: larga, para formulários e listas. */
    width?: 'nav' | 'panel'
  }>(),
  { side: 'start', width: 'nav' },
)

const open = defineModel<boolean>('open', { default: false })
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <AnimatePresence>
        <DialogOverlay v-if="open" force-mount as-child>
          <motion.div class="drawer__overlay" v-bind="fade" />
        </DialogOverlay>
      </AnimatePresence>
      <AnimatePresence>
        <DialogContent v-if="open" force-mount as-child :aria-describedby="undefined">
          <motion.div
            class="drawer"
            :class="[`drawer--${props.side}`, `drawer--${props.width}`]"
            v-bind="props.side === 'end' ? slideInEnd : slideInStart"
          >
            <DialogTitle class="drawer__title">{{ props.title }}</DialogTitle>
            <DialogClose as-child>
              <AppIconButton icon="x" :label="common.close" size="sm" class="drawer__close" />
            </DialogClose>
            <slot />
          </motion.div>
        </DialogContent>
      </AnimatePresence>
    </DialogPortal>
  </DialogRoot>
</template>

<style scoped lang="scss">
.drawer {
  position: fixed;
  inset-block: 0;
  z-index: var(--layer-dialog);
  display: flex;
  flex-direction: column;
  width: min(var(--drawer-width), calc(100% - var(--space-12)));
  overflow-y: auto;
  overscroll-behavior: contain;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);

  &--nav {
    --drawer-width: var(--sidebar-width);
  }

  &--panel {
    --drawer-width: var(--drawer-panel-width);
  }

  &--start {
    inset-inline-start: 0;
    border-inline-end: var(--border-width) solid var(--color-border);
  }

  &--end {
    inset-inline-end: 0;
    border-inline-start: var(--border-width) solid var(--color-border);
  }

  &__overlay {
    position: fixed;
    inset: 0;
    z-index: var(--layer-overlay);
    background: var(--color-overlay);
  }

  &__title {
    @include visually-hidden;
  }

  &__close {
    position: absolute;
    inset-block-start: var(--space-3);
    inset-inline-end: var(--space-3);
  }
}
</style>
