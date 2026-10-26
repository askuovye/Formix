<script setup lang="ts">
import { shell } from '@/strings'

/** Primeiro item focável da página: leva o teclado direto ao conteúdo principal. */
defineProps<{
  /** Id do <main> de destino. */
  target: string
}>()
</script>

<template>
  <a :href="`#${target}`" class="skip-link">{{ shell.skipToContent }}</a>
</template>

<style scoped lang="scss">
.skip-link {
  @include visually-hidden;

  &:focus {
    position: fixed;
    inset-block-start: var(--space-2);
    inset-inline-start: var(--space-2);
    z-index: var(--layer-tooltip);
    width: auto;
    height: auto;
    padding: var(--space-2) var(--space-3);
    overflow: visible;
    clip: auto;
    color: var(--color-primary-contrast);
    white-space: normal;
    background: var(--color-primary);
    border-radius: var(--radius-md);
  }
}
</style>
