<script setup lang="ts">
import { RouterView } from 'vue-router'

import AppSkipLink from '@/components/AppSkipLink.vue'

/**
 * Página de quem responde: o formulário é o protagonista, sem o cabeçalho do
 * app. Fundo, cartão e rodapé vêm do FormPage, com o tema do dono.
 */
</script>

<template>
  <div class="respondent-layout">
    <AppSkipLink target="conteudo" />
    <main id="conteudo" class="respondent-layout__main" tabindex="-1">
      <RouterView />
    </main>
  </div>
</template>

<style scoped lang="scss">
.respondent-layout {
  display: grid;
  min-block-size: 100dvh;

  &__main {
    display: grid;
    outline: none;
  }
}
</style>
