<script setup lang="ts">
import { computed } from 'vue'

import { useThemeStore, type ThemePreference } from '@/stores/useThemeStore'
import { theme as t } from '@/strings'

import AppDropdownMenu, { type DropdownMenuEntry } from './AppDropdownMenu.vue'
import AppIconButton from './AppIconButton.vue'

const theme = useThemeStore()

const items: DropdownMenuEntry[] = [
  { type: 'item', id: 'system', label: t.system, icon: 'monitor' },
  { type: 'item', id: 'light', label: t.light, icon: 'sun' },
  { type: 'item', id: 'dark', label: t.dark, icon: 'moon' },
]

const preference = computed({
  get: () => theme.preference,
  set: (value: string) => {
    if (isThemePreference(value)) {
      theme.setPreference(value)
    }
  },
})

function isThemePreference(value: string): value is ThemePreference {
  return value === 'system' || value === 'light' || value === 'dark'
}
</script>

<template>
  <AppDropdownMenu v-model="preference" :items="items" selectable>
    <AppIconButton :icon="theme.resolved === 'light' ? 'sun' : 'moon'" :label="t.label" />
  </AppDropdownMenu>
</template>
