<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuItemIndicator,
  DropdownMenuPortal,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from 'reka-ui'
import { ref } from 'vue'

import { distance, scaleIn } from '@/motion'

import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

export type DropdownMenuEntry =
  | {
      type: 'item'
      id: string
      label: string
      icon?: IconName
      danger?: boolean
      disabled?: boolean
    }
  | { type: 'separator' }

/**
 * Menu suspenso. Dois modos:
 * - ações (padrão): emite `select` com o id do item;
 * - `selectable`: escolha única com v-model (itens viram menuitemradio).
 * O gatilho vem pelo slot (ex.: AppIconButton).
 */
const props = withDefaults(
  defineProps<{
    items: readonly DropdownMenuEntry[]
    selectable?: boolean
    align?: 'start' | 'center' | 'end'
  }>(),
  { align: 'end' },
)

const model = defineModel<string>()

const emit = defineEmits<{
  select: [id: string]
}>()

const open = ref(false)
</script>

<template>
  <DropdownMenuRoot v-model:open="open">
    <DropdownMenuTrigger as-child>
      <slot />
    </DropdownMenuTrigger>
    <DropdownMenuPortal>
      <AnimatePresence>
        <DropdownMenuContent
          v-if="open"
          force-mount
          as-child
          :align="props.align"
          :side-offset="distance.sm"
        >
          <motion.div class="dropdown-menu" v-bind="scaleIn">
            <DropdownMenuRadioGroup v-if="props.selectable" v-model="model">
              <template
                v-for="(entry, index) in props.items"
                :key="entry.type === 'item' ? entry.id : `sep-${index}`"
              >
                <DropdownMenuSeparator
                  v-if="entry.type === 'separator'"
                  class="dropdown-menu__separator"
                />
                <DropdownMenuRadioItem
                  v-else
                  :value="entry.id"
                  :disabled="entry.disabled"
                  class="dropdown-menu__item"
                >
                  <AppIcon v-if="entry.icon" :name="entry.icon" class="dropdown-menu__icon" />
                  <span class="dropdown-menu__label">{{ entry.label }}</span>
                  <DropdownMenuItemIndicator class="dropdown-menu__indicator">
                    <AppIcon name="check" />
                  </DropdownMenuItemIndicator>
                </DropdownMenuRadioItem>
              </template>
            </DropdownMenuRadioGroup>

            <template v-else>
              <template
                v-for="(entry, index) in props.items"
                :key="entry.type === 'item' ? entry.id : `sep-${index}`"
              >
                <DropdownMenuSeparator
                  v-if="entry.type === 'separator'"
                  class="dropdown-menu__separator"
                />
                <DropdownMenuItem
                  v-else
                  :disabled="entry.disabled"
                  class="dropdown-menu__item"
                  :class="{ 'dropdown-menu__item--danger': entry.danger }"
                  @select="emit('select', entry.id)"
                >
                  <AppIcon v-if="entry.icon" :name="entry.icon" class="dropdown-menu__icon" />
                  <span class="dropdown-menu__label">{{ entry.label }}</span>
                </DropdownMenuItem>
              </template>
            </template>
          </motion.div>
        </DropdownMenuContent>
      </AnimatePresence>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>

<style scoped lang="scss">
.dropdown-menu {
  @include popover-surface;

  z-index: var(--layer-popover);
  transform-origin: var(--reka-dropdown-menu-content-transform-origin);

  &__item {
    @include popover-item;

    &--danger {
      color: var(--color-danger);

      &[data-highlighted] {
        background: var(--color-danger-soft);
      }
    }
  }

  &__icon {
    color: var(--color-text-muted);
  }

  &__item--danger &__icon {
    color: inherit;
  }

  &__label {
    flex: 1;
  }

  &__indicator {
    display: inline-flex;
  }

  &__separator {
    height: var(--border-width);
    margin-block: var(--space-1);
    background: var(--color-border);
  }
}
</style>
