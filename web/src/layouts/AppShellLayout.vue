<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'

import { resendEmailVerification } from '@/api/auth'
import { toApiError } from '@/api/client'
import AppBrand from '@/components/AppBrand.vue'
import AppButton from '@/components/AppButton.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import AppDropdownMenu, { type DropdownMenuEntry } from '@/components/AppDropdownMenu.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import AppSkipLink from '@/components/AppSkipLink.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { useSessionStore } from '@/stores/useSessionStore'
import { useToastStore } from '@/stores/useToastStore'
import { shell } from '@/strings'

import ExportTracker from '@/features/submissions/ExportTracker.vue'

import ShellNav from './ShellNav.vue'

/** Estrutura do painel do dono: barra lateral, topo e conteúdo. O login é garantido pelo router. */
const session = useSessionStore()
const toasts = useToastStore()
const route = useRoute()
const router = useRouter()

const drawerOpen = ref(false)
let loggingOut = false

const initials = computed(() =>
  (session.user?.name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase())
    .join(''),
)

const accountItems: DropdownMenuEntry[] = [
  { type: 'item', id: 'settings', label: shell.nav.settings, icon: 'settings' },
  { type: 'separator' },
  { type: 'item', id: 'logout', label: shell.logout, icon: 'log-out' },
]

async function onAccountSelect(id: string): Promise<void> {
  if (id === 'settings') {
    await router.push('/painel/configuracoes')
    return
  }

  loggingOut = true
  // Mesmo se a API falhar, a sessão local já foi encerrada.
  await session.logout().catch(() => undefined)
  toasts.show({ title: shell.loggedOut })
  await router.push({ name: 'login' })
}

// Sessão expirada no meio do uso (401 em qualquer chamada): volta ao login e depois para cá.
watch(
  () => session.status,
  async (status) => {
    if (status === 'guest' && !loggingOut) {
      toasts.show({ title: shell.sessionExpired })
      await router.replace({ name: 'login', query: { redirect: route.fullPath } })
    }
  },
)

const resend = useMutation({
  mutationFn: resendEmailVerification,
  onSuccess: (message) =>
    toasts.show({ title: shell.verifyEmail.sent, description: message, tone: 'success' }),
  onError: (error) => toasts.show({ title: toApiError(error).message, tone: 'danger' }),
})
</script>

<template>
  <div class="shell">
    <AppSkipLink target="conteudo" />

    <aside class="shell__sidebar">
      <AppBrand to="/painel" class="shell__brand" />
      <ShellNav />
    </aside>

    <AppDrawer v-model:open="drawerOpen" :title="shell.nav.label">
      <div class="shell__drawer">
        <AppBrand to="/painel" class="shell__brand" />
        <ShellNav @navigate="drawerOpen = false" />
      </div>
    </AppDrawer>

    <div class="shell__body">
      <header class="shell__topbar">
        <AppIconButton
          icon="menu"
          :label="shell.openMenu"
          class="shell__menu-button"
          @click="drawerOpen = true"
        />
        <div class="shell__actions">
          <ThemeToggle />
          <AppDropdownMenu v-if="session.user" :items="accountItems" @select="onAccountSelect">
            <button type="button" class="shell__avatar" :aria-label="shell.accountMenu">
              {{ initials }}
            </button>
          </AppDropdownMenu>
        </div>
      </header>

      <div v-if="session.user && !session.user.email_verified" class="shell__banner" role="status">
        <AppIcon name="mail" class="shell__banner-icon" />
        <span class="shell__banner-text">{{ shell.verifyEmail.text }}</span>
        <AppButton
          size="sm"
          variant="secondary"
          :loading="resend.isPending.value"
          @click="resend.mutate()"
        >
          {{ shell.verifyEmail.action }}
        </AppButton>
      </div>

      <main id="conteudo" class="shell__main" tabindex="-1">
        <RouterView />
      </main>
      <ExportTracker />
    </div>
  </div>
</template>

<style scoped lang="scss">
.shell {
  display: grid;
  min-height: 100dvh;

  @include up(lg) {
    grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
  }

  &__sidebar {
    display: none;

    @include up(lg) {
      position: sticky;
      inset-block-start: 0;
      display: flex;
      flex-direction: column;
      gap: var(--space-6);
      height: 100dvh;
      padding: var(--space-4) var(--space-3);
      background: var(--color-surface);
      border-inline-end: var(--border-width) solid var(--color-border);
    }
  }

  &__drawer {
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
    padding: var(--space-4) var(--space-3);
  }

  &__brand {
    padding-inline: var(--space-3);
    min-height: var(--control-height-md);
  }

  &__body {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  &__topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: var(--topbar-height);
    padding-inline: var(--space-4);
    border-block-end: var(--border-width) solid var(--color-border);

    @include up(lg) {
      justify-content: flex-end;
      padding-inline: var(--space-8);
    }
  }

  &__menu-button {
    @include up(lg) {
      display: none;
    }
  }

  &__actions {
    display: flex;
    gap: var(--space-2);
    align-items: center;
  }

  &__avatar {
    display: inline-grid;
    place-items: center;
    width: var(--control-height-sm);
    height: var(--control-height-sm);
    padding: 0;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-contrast);
    background: var(--color-primary);
    border: 0;
    border-radius: var(--radius-full);
    cursor: pointer;
  }

  &__banner {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-3) var(--space-4);
    font-size: var(--font-size-sm);
    background: var(--color-warning-soft);
    border-block-end: var(--border-width) solid var(--color-border);

    @include up(lg) {
      padding-inline: var(--space-8);
    }
  }

  &__banner-icon {
    font-size: var(--font-size-lg);
    color: var(--color-warning);
  }

  &__banner-text {
    flex: 1;
  }

  &__main {
    flex: 1;
    width: 100%;
    max-width: var(--layout-width-lg);
    padding: var(--space-8) var(--space-4);
    margin-inline: auto;
    outline: none;

    @include up(lg) {
      padding-inline: var(--space-8);
    }
  }
}
</style>
