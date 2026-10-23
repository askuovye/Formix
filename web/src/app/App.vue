<script setup lang="ts">
import { MotionConfig } from 'motion-v'
import { RouterView } from 'vue-router'
</script>

<template>
  <!-- reducedMotion="user" respeita prefers-reduced-motion em todas as animações do Motion. -->
  <MotionConfig reduced-motion="user">
    <RouterView />
  </MotionConfig>
</template>
