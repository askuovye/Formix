<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { BRAND_NAME, REPOSITORY_URL } from '@/config/brand'
import { landing } from '@/strings'

/** Rodapé mínimo: textos legais (LGPD), repositório e a marca. Cresce depois. */
const t = landing.footer
const year = new Date().getFullYear()
</script>

<template>
  <footer class="landing-footer">
    <p class="landing-footer__rights">{{ t.rights(BRAND_NAME, year) }}</p>
    <nav class="landing-footer__links" :aria-label="t.label">
      <RouterLink :to="{ name: 'terms' }" class="landing-footer__link">{{ t.terms }}</RouterLink>
      <RouterLink :to="{ name: 'privacy' }" class="landing-footer__link">
        {{ t.privacy }}
      </RouterLink>
      <a :href="REPOSITORY_URL" class="landing-footer__link" target="_blank" rel="noopener">
        {{ t.github }}
      </a>
    </nav>
  </footer>
</template>

<style scoped lang="scss">
.landing-footer {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4) var(--space-6);
  align-items: center;
  justify-content: space-between;
  inline-size: 100%;
  max-inline-size: var(--layout-width-lg);
  padding: var(--space-8) var(--space-4);
  margin-inline: auto;
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);

  @include up(md) {
    padding-inline: var(--space-8);
  }

  &__links {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-6);
  }

  &__link {
    color: inherit;
    text-decoration: none;
    border-radius: var(--radius-xs);

    &:hover {
      color: var(--color-text);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }
}
</style>
