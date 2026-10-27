<script setup lang="ts">
import { computed } from 'vue'

import { results } from '@/strings'
import type { FormResults } from '@/types/api'

import ChoiceBars from './ChoiceBars.vue'

/**
 * Aparelhos de quem respondeu, em barras como as escolhas (uma cor só, contagem
 * e % escritas). O % é sobre as respostas com aparelho conhecido.
 */
const props = defineProps<{
  devices: FormResults['devices']
}>()

const t = results.devices

const options = computed(() =>
  props.devices.map((item) => ({
    value: item.device,
    label: t.labels[item.device],
    count: item.count,
  })),
)
const known = computed(() => props.devices.reduce((sum, item) => sum + item.count, 0))
</script>

<template>
  <section class="devices" aria-labelledby="results-devices-title">
    <h3 id="results-devices-title" class="devices__title">{{ t.title }}</h3>
    <p v-if="known === 0" class="devices__empty">{{ t.empty }}</p>
    <ChoiceBars v-else :options="options" :answered="known" />
  </section>
</template>

<style scoped lang="scss">
.devices {
  display: grid;
  gap: var(--space-4);
  align-content: start;

  &__title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__empty {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
