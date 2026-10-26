<script setup lang="ts">
import { computed } from 'vue'

import AppBadge from '@/components/AppBadge.vue'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import type { IconName } from '@/components/icons/iconNames'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { builder, forms } from '@/strings'
import type { Form } from '@/types/api'

import BuilderPublishControl from './BuilderPublishControl.vue'
import type { SaveStatus } from './useAutosave'
import { useBuilderStore } from './useBuilderStore'

const props = defineProps<{
  /** Título em edição (o rascunho, não o salvo). */
  title: string
  form: Form
  saveStatus: SaveStatus
  publishing: boolean
  closing: boolean
}>()

const emit = defineEmits<{
  retrySave: []
  openMenu: []
  openProperties: []
  publish: []
  preview: []
  close: [done: (closed: boolean) => void]
}>()

const store = useBuilderStore()
const t = builder

const save = computed<{ icon: IconName; text: string; tone: 'muted' | 'danger' }>(() => {
  switch (props.saveStatus) {
    case 'saving':
      return { icon: 'loader-circle', text: t.save.saving, tone: 'muted' }
    case 'pending':
      return { icon: 'pencil', text: t.save.unsaved, tone: 'muted' }
    case 'error':
      return { icon: 'cloud-off', text: t.save.error, tone: 'danger' }
    case 'invalid':
      return { icon: 'circle-alert', text: t.save.invalid, tone: 'danger' }
    default:
      return { icon: 'check', text: t.save.saved, tone: 'muted' }
  }
})
</script>

<template>
  <header class="builder-topbar">
    <AppIconButton
      icon="menu"
      :label="t.rail.menu"
      class="builder-topbar__menu"
      @click="emit('openMenu')"
    />
    <AppButton
      :to="{ name: 'forms' }"
      variant="ghost"
      size="sm"
      icon-left="arrow-left"
      class="builder-topbar__back"
      :aria-label="t.back"
    />

    <div class="builder-topbar__heading">
      <h1 class="builder-topbar__title">{{ props.title }}</h1>
      <AppBadge class="builder-topbar__badge">{{ forms.status[props.form.status] }}</AppBadge>
    </div>

    <p
      class="builder-topbar__save"
      :class="`builder-topbar__save--${save.tone}`"
      role="status"
      aria-live="polite"
    >
      <AppSpinner v-if="props.saveStatus === 'saving'" />
      <AppIcon v-else :name="save.icon" />
      <span class="builder-topbar__save-text">{{ save.text }}</span>
      <button
        v-if="props.saveStatus === 'error'"
        type="button"
        class="builder-topbar__retry"
        @click="emit('retrySave')"
      >
        {{ t.save.retry }}
      </button>
    </p>

    <div class="builder-topbar__actions">
      <span class="builder-topbar__history">
        <AppIconButton
          icon="undo-2"
          :label="t.toolbar.undo"
          :disabled="!store.canUndo"
          @click="store.undo()"
        />
        <AppIconButton
          icon="redo-2"
          :label="t.toolbar.redo"
          :disabled="!store.canRedo"
          @click="store.redo()"
        />
      </span>
      <AppIconButton
        icon="sliders-horizontal"
        :label="t.toolbar.openProperties"
        class="builder-topbar__mobile"
        @click="emit('openProperties')"
      />
      <AppIconButton
        icon="eye"
        :label="t.publish.preview"
        class="builder-topbar__preview"
        @click="emit('preview')"
      />
      <BuilderPublishControl
        :form="props.form"
        :publishing="props.publishing"
        :closing="props.closing"
        @publish="emit('publish')"
        @preview="emit('preview')"
        @close="(done) => emit('close', done)"
      />
      <span class="builder-topbar__theme"><ThemeToggle /></span>
    </div>
  </header>
</template>

<style scoped lang="scss">
.builder-topbar {
  display: flex;
  gap: var(--space-2);
  align-items: center;
  height: var(--topbar-height);
  padding-inline: var(--space-2);
  background: var(--color-surface);
  border-block-end: var(--border-width) solid var(--color-border);

  @include up(sm) {
    gap: var(--space-3);
    padding-inline: var(--space-3);
  }

  &__heading {
    display: flex;
    flex: 1;
    gap: var(--space-2);
    align-items: center;
    min-width: 0;
  }

  &__title {
    overflow: hidden;
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__save {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-xs);
    white-space: nowrap;

    &--muted {
      color: var(--color-text-muted);
    }

    &--danger {
      color: var(--color-danger);
    }
  }

  // No celular só o ícone; o texto continua para leitores de tela.
  &__save-text {
    @include visually-hidden;

    @include up(md) {
      position: static;
      width: auto;
      height: auto;
      overflow: visible;
      clip: auto;
      white-space: nowrap;
    }
  }

  &__retry {
    padding: 0;
    font: inherit;
    font-weight: var(--font-weight-medium);
    color: inherit;
    text-decoration: underline;
    background: none;
    border: 0;
    cursor: pointer;

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__actions {
    display: flex;
    gap: 0;
    align-items: center;
  }

  // Abaixo de lg o rail e as propriedades viram gavetas, abertas por estes botões.
  &__mobile,
  &__menu {
    @include up(lg) {
      display: none;
    }
  }

  // No celular o "voltar" fica na gaveta do menu e desfazer/refazer flutuam no canto.
  &__back,
  &__history {
    display: none;

    @include up(md) {
      display: inline-flex;
    }
  }

  // No celular, "Visualizar" fica no menu "⋯" da publicação.
  &__preview {
    display: none;

    @include up(sm) {
      display: inline-flex;
    }
  }

  // Pouco espaço no celular: a situação e o tema aparecem a partir de telas médias.
  &__badge,
  &__theme {
    display: none;

    @include up(md) {
      display: inline-flex;
    }
  }
}
</style>
