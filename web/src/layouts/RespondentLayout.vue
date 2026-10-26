<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import AppSkipLink from '@/components/AppSkipLink.vue'
import { BRAND_NAME } from '@/config/brand'
import { renderer } from '@/strings'

/**
 * Página de quem responde: o formulário é o protagonista, sem o cabeçalho do
 * app. O tema do dono (fase 4c) vale dentro deste layout.
 */
</script>

<template>
  <div class="respondent-layout">
    <AppSkipLink target="conteudo" />
    <main id="conteudo" class="respondent-layout__main" tabindex="-1">
      <RouterView />
    </main>
    <footer class="respondent-layout__footer">
      <RouterLink to="/" class="respondent-layout__brand">
        {{ renderer.madeWith(BRAND_NAME) }}
      </RouterLink>
    </footer>
  </div>
</template>

<style scoped lang="scss">
.respondent-layout {
  display: grid;
  grid-template-rows: 1fr auto;
  min-block-size: 100dvh;

  &__main {
    inline-size: 100%;
    max-inline-size: var(--layout-width-md);
    padding: var(--space-10) var(--space-4);
    margin-inline: auto;
    outline: none;

    @include up(md) {
      padding-block: var(--space-16);
    }
  }

  &__footer {
    display: flex;
    justify-content: center;
    padding: var(--space-6) var(--space-4);
  }

  &__brand {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
    text-decoration: none;

    &:hover {
      color: var(--color-text-muted);
    }
  }
}
</style>
