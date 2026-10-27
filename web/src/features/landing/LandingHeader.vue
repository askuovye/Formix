<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppBrand from '@/components/AppBrand.vue'
import AppButton from '@/components/AppButton.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { REPOSITORY_URL } from '@/config/brand'
import { landing } from '@/strings'

/** Topo da landing: marca, Recursos e GitHub, e entrar/criar conta (ou o painel, se logado). */
const props = defineProps<{
  authenticated: boolean
}>()

const t = landing.nav
</script>

<template>
  <header class="landing-header">
    <AppBrand to="/" />

    <nav class="landing-header__nav" :aria-label="t.label">
      <RouterLink :to="{ hash: '#recursos' }" class="landing-header__link">
        {{ t.features }}
      </RouterLink>
      <a :href="REPOSITORY_URL" class="landing-header__link" target="_blank" rel="noopener">
        {{ t.github }}
      </a>
    </nav>

    <div class="landing-header__actions">
      <ThemeToggle />
      <AppButton v-if="props.authenticated" :to="{ name: 'forms' }" size="sm">
        {{ t.panel }}
      </AppButton>
      <template v-else>
        <AppButton :to="{ name: 'login' }" variant="ghost" size="sm" class="landing-header__login">
          {{ t.login }}
        </AppButton>
        <AppButton :to="{ name: 'register' }" size="sm">{{ t.register }}</AppButton>
      </template>
    </div>
  </header>
</template>

<style scoped lang="scss">
.landing-header {
  display: flex;
  gap: var(--space-4);
  align-items: center;
  inline-size: 100%;
  max-inline-size: var(--layout-width-lg);
  block-size: var(--topbar-height);
  padding-inline: var(--space-4);
  margin-inline: auto;

  @include up(md) {
    padding-inline: var(--space-8);
  }

  // Celular: só marca e ações; Recursos e GitHub ficam no rodapé e no "Ver como funciona".
  &__nav {
    display: none;

    @include up(md) {
      display: flex;
      gap: var(--space-6);
      margin-inline-start: auto;
    }
  }

  &__link {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    text-decoration: none;
    border-radius: var(--radius-xs);
    transition: color var(--duration-fast) var(--ease-out);

    &:hover {
      color: var(--color-text);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__actions {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    margin-inline-start: auto;

    @include up(md) {
      margin-inline-start: var(--space-6);
    }
  }

  // Celular estreito: "Criar conta" basta (Entrar fica na própria tela de cadastro).
  &__login {
    display: none;

    @include up(sm) {
      display: inline-flex;
    }
  }
}
</style>
