<script setup lang="ts">
import { Icon } from '@iconify/vue'

const props = defineProps<{
  /** Nome Iconify no formato `colecao:nome` (ex.: `lucide:trash-2`). */
  name: string
  /** Texto para leitores de tela. Sem ele, o ícone é decorativo (aria-hidden). */
  label?: string
}>()
</script>

<template>
  <Icon
    :icon="props.name"
    class="app-icon"
    :role="props.label ? 'img' : undefined"
    :aria-label="props.label"
    :aria-hidden="props.label ? undefined : 'true'"
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
