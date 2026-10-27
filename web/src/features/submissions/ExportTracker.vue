<script setup lang="ts">
import { useExportsStore } from '@/stores/useExportsStore'

import ExportWatcher from './ExportWatcher.vue'

/** Montado uma vez no painel: acompanha as exportações mesmo com a janela fechada. */
const exportsStore = useExportsStore()
</script>

<template>
  <ExportWatcher v-for="item in exportsStore.tracked" :key="item.id" :item="item" />
</template>
