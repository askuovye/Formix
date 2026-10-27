<script setup lang="ts">
import { computed, onMounted } from 'vue'

import AppSkipLink from '@/components/AppSkipLink.vue'
import LandingFeatures from '@/features/landing/LandingFeatures.vue'
import LandingFooter from '@/features/landing/LandingFooter.vue'
import LandingHeader from '@/features/landing/LandingHeader.vue'
import LandingHero from '@/features/landing/LandingHero.vue'
import LandingShowcase from '@/features/landing/LandingShowcase.vue'
import { useSessionStore } from '@/stores/useSessionStore'

/** Página inicial: aberta a todos; com sessão, o topo leva ao painel em vez de entrar/criar conta. */
const session = useSessionStore()
const authenticated = computed(() => session.isAuthenticated)

// Sem esperar: a página aparece na hora e o botão do topo se ajusta quando a sessão responder.
onMounted(() => {
  session.load().catch(() => undefined)
})
</script>

<template>
  <div class="landing">
    <AppSkipLink target="conteudo" />
    <LandingHeader :authenticated="authenticated" />
    <main id="conteudo" class="landing__main" tabindex="-1">
      <LandingHero />
      <LandingFeatures />
      <LandingShowcase />
    </main>
    <LandingFooter />
  </div>
</template>

<style scoped lang="scss">
.landing {
  display: grid;
  grid-template-rows: auto 1fr auto;
  min-block-size: 100dvh;
  // As camadas inclinadas do hero não podem criar rolagem lateral no celular.
  overflow-x: clip;

  &__main {
    inline-size: 100%;
    max-inline-size: var(--layout-width-lg);
    padding-inline: var(--space-4);
    padding-block-end: var(--space-16);
    margin-inline: auto;
    outline: none;

    @include up(md) {
      padding-inline: var(--space-8);
    }
  }
}
</style>
