<script lang="ts">
import { addCollection } from '@iconify/vue/offline'

import lucideSubset from './icons/lucide-subset.json'

// Registro único, no carregamento do módulo. O build offline do Iconify não
// tem código de API: nenhum ícone é buscado em runtime.
addCollection(lucideSubset)
</script>

<script setup lang="ts">
import { Icon } from '@iconify/vue/offline'

import type { IconName } from './icons/iconNames'

const props = defineProps<{
  /** Nome do ícone Lucide (lista em icons/iconNames.ts). */
  name: IconName
  /** Texto para leitores de tela. Sem ele, o ícone é decorativo (aria-hidden). */
  label?: string
}>()
</script>

<template>
  <Icon
    :icon="`lucide:${props.name}`"
    class="app-icon"
    :role="props.label ? 'img' : undefined"
    :aria-label="props.label"
    :aria-hidden="!props.label"
  />
</template>

<style scoped lang="scss">
.app-icon {
  display: inline-block;
  width: 1em;
  height: 1em;
  flex-shrink: 0;
}
</style>
