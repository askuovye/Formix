<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
  DialogTrigger,
} from 'reka-ui'

import { fade, scaleIn } from '@/motion'
import { common } from '@/strings'

import AppIconButton from './AppIconButton.vue'

/**
 * Janela modal. Abre pelo slot `trigger` ou por v-model:open.
 * O slot `footer` recebe `close` para os botões de ação.
 */
const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    /** `full`: quase a tela toda, com o corpo rolando por dentro (pré-visualização). */
    size?: 'md' | 'full'
  }>(),
  { size: 'md' },
)

const open = defineModel<boolean>('open', { default: false })

defineSlots<{
  trigger?: () => unknown
  default?: () => unknown
  footer?: (scope: { close: () => void }) => unknown
}>()

function close(): void {
  open.value = false
}
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogTrigger v-if="$slots.trigger" as-child>
      <slot name="trigger" />
    </DialogTrigger>
    <DialogPortal>
      <AnimatePresence>
        <DialogOverlay v-if="open" force-mount as-child>
          <motion.div class="dialog__overlay" v-bind="fade" />
        </DialogOverlay>
      </AnimatePresence>
      <AnimatePresence>
        <!-- Sem descrição, aria-describedby: undefined avisa o Reka que a omissão é intencional. -->
        <DialogContent
          v-if="open"
          force-mount
          as-child
          v-bind="props.description ? {} : { 'aria-describedby': undefined }"
        >
          <motion.div class="dialog" :class="`dialog--${props.size}`" v-bind="scaleIn">
            <header class="dialog__header">
              <DialogTitle class="dialog__title">{{ props.title }}</DialogTitle>
              <DialogClose as-child>
                <AppIconButton icon="x" :label="common.close" size="sm" />
              </DialogClose>
            </header>
            <DialogDescription v-if="props.description" class="dialog__description">
              {{ props.description }}
            </DialogDescription>
            <div v-if="$slots.default" class="dialog__body">
              <slot />
            </div>
            <footer v-if="$slots.footer" class="dialog__footer">
              <slot name="footer" :close="close" />
            </footer>
          </motion.div>
        </DialogContent>
      </AnimatePresence>
    </DialogPortal>
  </DialogRoot>
</template>

<style scoped lang="scss">
.dialog {
  position: fixed;
  inset: 0;
  z-index: var(--layer-dialog);
  display: grid;
  gap: var(--space-4);
  width: min(var(--dialog-width-md), calc(100% - 2 * var(--space-4)));
  height: fit-content;
  max-height: calc(100% - 2 * var(--space-4));
  padding: var(--space-6);
  margin: auto;
  overflow-y: auto;
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);

  &--full {
    grid-template-rows: auto minmax(0, 1fr);
    width: min(var(--dialog-width-full), calc(100% - 2 * var(--space-4)));
    height: calc(100% - 2 * var(--space-4));
    overflow: hidden;
  }

  &--full &__body {
    min-height: 0;
  }

  &__overlay {
    position: fixed;
    inset: 0;
    z-index: var(--layer-overlay);
    background: var(--color-overlay);
  }

  &__header {
    display: flex;
    gap: var(--space-4);
    align-items: flex-start;
    justify-content: space-between;
  }

  &__title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-tight);
  }

  &__description {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__footer {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    justify-content: flex-end;
  }
}
</style>
