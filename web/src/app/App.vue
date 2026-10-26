<script setup lang="ts">
import { MotionConfig } from 'motion-v'
import { RouterView } from 'vue-router'

import { useThemeStore } from '@/stores/useThemeStore'

// Instancia a store já no início: mantém data-theme em sincronia com a
// escolha do usuário e com o tema do sistema.
useThemeStore()
</script>

<template>
  <!-- reducedMotion="user" respeita prefers-reduced-motion em todas as animações do Motion. -->
  <MotionConfig reduced-motion="user">
    <RouterView />
  </MotionConfig>
</template>
