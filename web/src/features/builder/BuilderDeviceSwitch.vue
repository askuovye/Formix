<script setup lang="ts">
import AppIconButton from '@/components/AppIconButton.vue'
import type { IconName } from '@/components/icons/iconNames'
import { builder } from '@/strings'

import { CANVAS_DEVICES, type CanvasDevice, useCanvasDevice } from './canvasDevice'

/** Barrinha flutuante sob o canvas: ver o formulário na largura do computador ou do celular. */
const t = builder.canvas.device
const { device, setDevice } = useCanvasDevice()

const icons: Record<CanvasDevice, IconName> = { desktop: 'monitor', mobile: 'smartphone' }
</script>

<template>
  <div class="device-switch" role="group" :aria-label="t.label">
    <AppIconButton
      v-for="item in CANVAS_DEVICES"
      :key="item"
      :icon="icons[item]"
      :label="t[item]"
      :title="t[item]"
      size="sm"
      :aria-pressed="device === item"
      @click="setDevice(item)"
    />
  </div>
</template>

<style scoped lang="scss">
.device-switch {
  display: flex;
  gap: var(--space-1);
  padding: var(--space-1);
  background: var(--color-surface-raised);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-full);
  box-shadow: var(--shadow-lg);
}
</style>
