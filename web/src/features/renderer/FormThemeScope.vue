<script setup lang="ts">
import { computed } from 'vue'

import type { FormTheme } from '@/types/schema'

import { resolveTheme, themeClasses, themeStyle } from './theme'

/**
 * Aplica o tema do formulário a tudo que estiver dentro: cores-base no
 * atributo style e o resto derivado aqui (color-roles), mais raio, fonte e
 * alinhamento. Usado na página pública, na pré-visualização e no canvas.
 */
const props = withDefaults(
  defineProps<{
    theme?: Partial<FormTheme> | null
    tag?: string
  }>(),
  { theme: null, tag: 'div' },
)

const resolved = computed(() => resolveTheme(props.theme))
</script>

<template>
  <component
    :is="props.tag"
    class="form-theme"
    :class="themeClasses(resolved)"
    :style="themeStyle(resolved)"
  >
    <slot />
  </component>
</template>

<style scoped lang="scss">
@use '@/styles/themes/dark';
@use '@/styles/themes/light';

// As cores-base chegam no atributo style; o resto é derivado no mesmo elemento.
// Cores de estado, sombras e controles nativos seguem a luz do fundo.
.form-theme {
  @include color-roles;

  font-family: var(--font-family-base);
  color: var(--color-text);
  background: var(--color-bg);

  &--light {
    @include light.values;
  }

  &--dark {
    @include dark.values;
  }

  &--font-system {
    --font-family-base: var(--font-family-system);
  }

  &--font-serif {
    --font-family-base: var(--font-family-serif);
  }

  &--radius-none {
    --radius-sm: 0;
    --radius-md: 0;
    --radius-lg: 0;
    --radius-xl: 0;
  }

  &--radius-small {
    --radius-sm: 2px;
    --radius-md: 4px;
    --radius-lg: 6px;
    --radius-xl: 8px;
  }

  &--radius-large {
    --radius-sm: 10px;
    --radius-md: 14px;
    --radius-lg: 20px;
    --radius-xl: 28px;
  }
}
</style>
