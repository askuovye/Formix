<script setup lang="ts">
import AppBadge from '@/components/AppBadge.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import { useSessionStore } from '@/stores/useSessionStore'
import { settingsPage } from '@/strings'

const session = useSessionStore()
</script>

<template>
  <AppPageHeader :title="settingsPage.title" :subtitle="settingsPage.subtitle" />
  <AppCard as="section" padding="lg" class="settings" aria-labelledby="settings-account">
    <h2 id="settings-account" class="settings__heading">{{ settingsPage.account }}</h2>
    <dl v-if="session.user" class="settings__list">
      <div class="settings__item">
        <dt class="settings__term">{{ settingsPage.name }}</dt>
        <dd class="settings__value">{{ session.user.name }}</dd>
      </div>
      <div class="settings__item">
        <dt class="settings__term">{{ settingsPage.email }}</dt>
        <dd class="settings__value">
          {{ session.user.email }}
          <AppBadge :tone="session.user.email_verified ? 'success' : 'warning'">
            {{ session.user.email_verified ? settingsPage.verified : settingsPage.notVerified }}
          </AppBadge>
        </dd>
      </div>
    </dl>
  </AppCard>
</template>

<style scoped lang="scss">
.settings {
  display: grid;
  gap: var(--space-4);
  max-width: var(--layout-width-md);

  &__heading {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
  }

  &__list {
    display: grid;
    gap: var(--space-4);
    margin: 0;
  }

  &__item {
    display: grid;
    gap: var(--space-1);
  }

  &__term {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__value {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
    align-items: center;
    margin: 0;
    font-size: var(--font-size-sm);
  }
}
</style>
