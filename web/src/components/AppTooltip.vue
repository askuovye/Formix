<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import {
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import { ref } from 'vue'

import { delay, distance, fade } from '@/motion'

/** Dica curta ao passar o mouse ou focar o elemento do slot (ex.: botão só com ícone). */
const props = withDefaults(
  defineProps<{
    content: string
    side?: 'top' | 'right' | 'bottom' | 'left'
  }>(),
  { side: 'top' },
)

const open = ref(false)
</script>

<template>
  <TooltipProvider :delay-duration="delay.tooltip">
    <TooltipRoot v-model:open="open">
      <TooltipTrigger as-child>
        <slot />
      </TooltipTrigger>
      <TooltipPortal>
        <AnimatePresence>
          <TooltipContent
            v-if="open"
            force-mount
            as-child
            :side="props.side"
            :side-offset="distance.sm"
          >
            <motion.div class="app-tooltip" v-bind="fade">{{ props.content }}</motion.div>
          </TooltipContent>
        </AnimatePresence>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>
</template>

<style scoped lang="scss">
.app-tooltip {
  z-index: var(--layer-tooltip);
  max-width: var(--tooltip-max-width);
  padding: var(--space-1) var(--space-2);
  font-size: var(--font-size-xs);
  line-height: var(--line-height-base);
  color: var(--color-primary-contrast);
  background: var(--color-primary);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-sm);
}
</style>
