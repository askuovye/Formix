<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import AppBrand from '@/components/AppBrand.vue'
import AppSkipLink from '@/components/AppSkipLink.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { shell } from '@/strings'

/** Páginas abertas: textos legais, 404 e, na fase 2a, as telas de autenticação. */
</script>

<template>
  <div class="public-layout">
    <AppSkipLink target="conteudo" />
    <header class="public-layout__header">
      <AppBrand to="/" />
      <ThemeToggle />
    </header>
    <main id="conteudo" class="public-layout__main" tabindex="-1">
      <RouterView />
    </main>
    <footer class="public-layout__footer">
      <RouterLink to="/termos" class="public-layout__link">{{ shell.footer.terms }}</RouterLink>
      <RouterLink to="/privacidade" class="public-layout__link">
        {{ shell.footer.privacy }}
      </RouterLink>
    </footer>
  </div>
</template>

<style scoped lang="scss">
.public-layout {
  display: grid;
  grid-template-rows: auto 1fr auto;
  min-height: 100dvh;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: var(--layout-width-lg);
    height: var(--topbar-height);
    padding-inline: var(--space-4);
    margin-inline: auto;

    @include up(md) {
      padding-inline: var(--space-8);
    }
  }

  &__main {
    width: 100%;
    max-width: var(--layout-width-lg);
    padding: var(--space-8) var(--space-4);
    margin-inline: auto;
    outline: none;

    @include up(md) {
      padding-inline: var(--space-8);
    }
  }

  &__footer {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-6);
    justify-content: center;
    padding: var(--space-6) var(--space-4);
    font-size: var(--font-size-sm);
    border-block-start: var(--border-width) solid var(--color-border);
  }

  &__link {
    color: var(--color-text-muted);
    text-decoration: none;

    &:hover {
      color: var(--color-text);
    }
  }
}
</style>
