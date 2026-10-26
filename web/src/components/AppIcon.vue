<script lang="ts">
import { addCollection } from '@iconify/vue/offline'

import lucideSubset from './icons/lucide-subset.json'

// Registro único, no carregamento do módulo. O build offline do Iconify não
// tem código de API: nenhum ícone é buscado em runtime.
addCollection(lucideSubset)
</script>

<script setup lang="ts">
import { Icon, type IconifyIcon } from '@iconify/vue/offline'
import { computed } from 'vue'

import type { IconName } from './icons/iconNames'

const props = defineProps<{
  /** Nome do ícone Lucide (lista em icons/iconNames.ts). */
  name: IconName
  /** Texto para leitores de tela. Sem ele, o ícone é decorativo (aria-hidden). */
  label?: string
  /** Preenche o desenho com a cor do texto (ex.: estrela marcada). */
  filled?: boolean
}>()

const ICON_SIZE = lucideSubset.width

// Os ícones Lucide são só contorno (fill="none"). Preenchido, o desenho vem da
// mesma coleção local com o atributo trocado (o build offline não tem customise).
const icon = computed<string | IconifyIcon>(() => {
  const data = (lucideSubset.icons as Record<string, { body: string } | undefined>)[props.name]
  if (!props.filled || !data) return `lucide:${props.name}`

  return {
    body: data.body.replace(/fill="none"/g, 'fill="currentColor"'),
    width: ICON_SIZE,
    height: ICON_SIZE,
  }
})
</script>

<template>
  <Icon
    :icon="icon"
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
