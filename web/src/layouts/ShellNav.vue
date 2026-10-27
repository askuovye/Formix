<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import type { IconName } from '@/components/icons/iconNames'
import { shell } from '@/strings'

/** Navegação principal do painel (barra lateral e gaveta no celular). */
const emit = defineEmits<{
  navigate: []
}>()

const links: { to: string; label: string; icon: IconName }[] = [
  { to: '/painel/formularios', label: shell.nav.forms, icon: 'file-text' },
  { to: '/painel/respostas', label: shell.nav.submissions, icon: 'inbox' },
  { to: '/painel/configuracoes', label: shell.nav.settings, icon: 'settings' },
]
</script>

<template>
  <nav class="shell-nav" :aria-label="shell.nav.label">
    <ul class="shell-nav__list">
      <li v-for="link in links" :key="link.to">
        <RouterLink
          :to="link.to"
          class="shell-nav__link"
          active-class="shell-nav__link--active"
          @click="emit('navigate')"
        >
          <AppIcon :name="link.icon" class="shell-nav__icon" />
          {{ link.label }}
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>

<style scoped lang="scss">
.shell-nav {
  &__list {
    display: grid;
    gap: var(--space-1);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__link {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    height: var(--control-height-md);
    padding-inline: var(--space-3);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
    text-decoration: none;
    border-radius: var(--radius-md);
    transition:
      background-color var(--duration-fast) var(--ease-out),
      color var(--duration-fast) var(--ease-out);

    &:hover {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }

    &--active {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }
  }

  &__icon {
    font-size: var(--font-size-lg);
  }
}
</style>
