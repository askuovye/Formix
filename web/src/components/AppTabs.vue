<script setup lang="ts">
import { TabsContent, TabsIndicator, TabsList, TabsRoot, TabsTrigger } from 'reka-ui'

import AppIcon from './AppIcon.vue'
import type { IconName } from './icons/iconNames'

export interface TabItem {
  value: string
  label: string
  icon?: IconName
  disabled?: boolean
}

/**
 * Abas. O conteúdo de cada aba vai no slot com o nome do `value`:
 * `<template #campo>…</template>`.
 */
const props = withDefaults(
  defineProps<{
    items: readonly TabItem[]
    /** Nome acessível da lista de abas. */
    label: string
    variant?: 'underline' | 'segmented'
  }>(),
  { variant: 'underline' },
)

const model = defineModel<string>()

defineSlots<Record<string, () => unknown>>()
</script>

<template>
  <TabsRoot v-model="model" class="tabs" :class="`tabs--${props.variant}`">
    <TabsList class="tabs__list" :aria-label="props.label">
      <TabsIndicator class="tabs__indicator" />
      <TabsTrigger
        v-for="item in props.items"
        :key="item.value"
        :value="item.value"
        :disabled="item.disabled"
        class="tabs__trigger"
      >
        <AppIcon v-if="item.icon" :name="item.icon" />
        {{ item.label }}
      </TabsTrigger>
    </TabsList>
    <TabsContent
      v-for="item in props.items"
      :key="item.value"
      :value="item.value"
      class="tabs__panel"
    >
      <slot :name="item.value" />
    </TabsContent>
  </TabsRoot>
</template>

<style scoped lang="scss">
.tabs {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-4);

  // Em telas estreitas as abas rolam na horizontal em vez de alargar a página.
  &__list {
    position: relative;
    display: flex;
    max-width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
  }

  &__trigger {
    position: relative;
    z-index: 1;
    display: inline-flex;
    gap: var(--space-2);
    align-items: center;
    justify-content: center;
    height: var(--control-height-sm);
    padding-inline: var(--space-3);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
    background: transparent;
    white-space: nowrap;
    border: 0;
    cursor: pointer;
    transition: color var(--duration-fast) var(--ease-out);

    &:hover:not([data-disabled]),
    &[data-state='active'] {
      color: var(--color-text);
    }

    &[data-disabled] {
      cursor: not-allowed;
      opacity: var(--opacity-disabled);
    }
  }

  // O Reka expõe posição e largura da aba ativa em custom properties.
  &__indicator {
    position: absolute;
    left: 0;
    width: var(--reka-tabs-indicator-size);
    transform: translateX(var(--reka-tabs-indicator-position));
    transition:
      transform var(--duration-base) var(--ease-out),
      width var(--duration-base) var(--ease-out);
  }

  &--underline &__list {
    border-block-end: var(--border-width) solid var(--color-border);
  }

  &--underline &__indicator {
    bottom: calc(-1 * var(--border-width));
    height: var(--border-width-strong);
    background: var(--color-primary);
    border-radius: var(--radius-full);
  }

  &--segmented &__list {
    width: fit-content;
    padding: var(--space-1);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &--segmented &__trigger {
    flex: 1 0 auto;
  }

  &--segmented &__indicator {
    top: var(--space-1);
    bottom: var(--space-1);
    background: var(--color-surface-hover);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
  }

  &__panel {
    outline: none;

    &:focus-visible {
      @include focus-ring;
    }
  }
}
</style>
