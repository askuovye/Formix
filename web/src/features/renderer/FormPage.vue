<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import { BRAND_NAME } from '@/config/brand'
import { renderer } from '@/strings'
import type { FormTheme } from '@/types/schema'

import FormThemeScope from './FormThemeScope.vue'
import { resolveTheme } from './theme'

/**
 * Página do formulário com o tema do dono: fundo, capa, cartão com logo,
 * título e descrição, e o conteúdo (campos, comprovante, avisos) no slot.
 * As cores valem só aqui dentro (custom properties no elemento raiz).
 */
const props = defineProps<{
  theme?: Partial<FormTheme> | null
  title?: string
  description?: string | null
  /** Esconde o rodapé "Feito com" (ex.: pré-visualização dentro do builder). */
  bare?: boolean
}>()

const resolved = computed(() => resolveTheme(props.theme))
const cover = computed(() => (resolved.value.cover?.url ? resolved.value.cover : null))
const logo = computed(() => resolved.value.logo?.url ?? null)
</script>

<template>
  <FormThemeScope :theme="resolved" class="form-page">
    <div v-if="cover" class="form-page__cover" :class="`form-page__cover--${cover.fit}`">
      <img class="form-page__cover-image" :src="cover.url" alt="" />
      <span
        class="form-page__cover-overlay"
        :style="{ opacity: cover.overlay_opacity / 100 }"
        aria-hidden="true"
      />
    </div>

    <div class="form-page__container" :class="{ 'form-page__container--with-cover': cover }">
      <article class="form-page__card">
        <header v-if="props.title" class="form-page__header">
          <img v-if="logo" class="form-page__logo" :src="logo" alt="" />
          <h1 class="form-page__title">{{ props.title }}</h1>
          <p v-if="props.description" class="form-page__description">{{ props.description }}</p>
        </header>
        <slot />
      </article>

      <footer v-if="!props.bare" class="form-page__footer">
        <RouterLink to="/" class="form-page__brand">{{ renderer.madeWith(BRAND_NAME) }}</RouterLink>
      </footer>
    </div>
  </FormThemeScope>
</template>

<style scoped lang="scss">
// Responde à largura da própria página (não da tela): a pré-visualização
// em "Celular" dentro do builder fica igual a um celular de verdade.
.form-page {
  min-block-size: 100%;
  container-type: inline-size;

  &__cover {
    position: relative;
    block-size: var(--form-cover-height);
    overflow: hidden;
    background: var(--color-surface-raised);

    @include container-up(md) {
      block-size: var(--form-cover-height-md);
    }
  }

  &__cover-image {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
  }

  &__cover--contain &__cover-image {
    object-fit: contain;
  }

  &__cover-overlay {
    position: absolute;
    inset: 0;
    background: var(--gray-900);
  }

  &__container {
    display: grid;
    gap: var(--space-6);
    inline-size: 100%;
    max-inline-size: var(--layout-width-md);
    padding: var(--space-8) var(--space-4);
    margin-inline: auto;

    @include container-up(md) {
      padding-block: var(--space-12);
    }

    // Com capa, o cartão sobe um pouco sobre ela.
    &--with-cover {
      position: relative;
      margin-block-start: calc(-1 * var(--space-16));
      padding-block-start: 0;
    }
  }

  &__card {
    display: grid;
    gap: var(--space-8);
    padding: var(--space-6) var(--space-4);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);

    @include container-up(md) {
      padding: var(--space-10);
    }
  }

  &__header {
    display: grid;
    gap: var(--space-3);
    justify-items: start;
  }

  .form-theme--align-center &__header {
    justify-items: center;
    text-align: center;
  }

  &__logo {
    max-inline-size: var(--form-logo-max-width);
    max-block-size: var(--form-logo-height);
    margin-block-end: var(--space-2);
    object-fit: contain;
  }

  &__title {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
    overflow-wrap: anywhere;
  }

  &__description {
    color: var(--color-text-muted);
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  &__footer {
    display: flex;
    justify-content: center;
  }

  &__brand {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
    text-decoration: none;

    &:hover {
      color: var(--color-text-muted);
    }
  }
}
</style>
