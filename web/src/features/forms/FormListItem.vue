<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import AppBadge from '@/components/AppBadge.vue'
import AppDropdownMenu, { type DropdownMenuEntry } from '@/components/AppDropdownMenu.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import { forms } from '@/strings'
import type { FormStatus, FormSummary } from '@/types/api'

import { relativeTime } from './relativeTime'

export type FormAction =
  'edit' | 'submissions' | 'export' | 'copy-link' | 'archive' | 'reopen' | 'delete'

const props = defineProps<{
  form: FormSummary
}>()

const emit = defineEmits<{
  action: [action: FormAction]
}>()

const t = forms

const tones: Record<FormStatus, 'success' | 'warning' | 'neutral'> = {
  published: 'success',
  draft: 'warning',
  closed: 'neutral',
}

const menu = computed<DropdownMenuEntry[]>(() => {
  const { status } = props.form
  const entries: DropdownMenuEntry[] = [
    { type: 'item', id: 'edit', label: t.actions.edit, icon: 'pencil' },
    { type: 'item', id: 'submissions', label: t.actions.submissions, icon: 'inbox' },
    { type: 'item', id: 'export', label: t.actions.export, icon: 'download' },
  ]

  if (status === 'published') {
    entries.push(
      { type: 'item', id: 'copy-link', label: t.actions.copyLink, icon: 'copy' },
      { type: 'item', id: 'archive', label: t.actions.archive, icon: 'archive' },
    )
  }

  if (status === 'closed') {
    entries.push({ type: 'item', id: 'reopen', label: t.actions.reopen, icon: 'rotate-ccw' })
  }

  entries.push(
    { type: 'separator' },
    { type: 'item', id: 'delete', label: t.actions.delete, icon: 'trash-2', danger: true },
  )

  return entries
})

// Rascunho sempre tem alterações não publicadas: o aviso só faz sentido depois de publicar.
const showUnpublished = computed(
  () => props.form.status !== 'draft' && props.form.has_unpublished_changes,
)
</script>

<template>
  <li class="form-item">
    <span class="form-item__icon">
      <AppIcon name="file-text" />
    </span>

    <div class="form-item__main">
      <RouterLink
        :to="{ name: 'form-builder', params: { id: props.form.id } }"
        class="form-item__title"
      >
        {{ props.form.title }}
      </RouterLink>
      <p class="form-item__meta">
        {{ t.updated(relativeTime(props.form.updated_at)) }}
        <template v-if="showUnpublished"> · {{ t.unpublishedChanges }}</template>
      </p>
    </div>

    <div class="form-item__details">
      <AppBadge :tone="tones[props.form.status]">{{ t.status[props.form.status] }}</AppBadge>
      <span class="form-item__count">{{ t.submissions(props.form.submissions_count) }}</span>
      <span v-if="props.form.last_submission_at" class="form-item__last">
        {{ t.lastSubmission(relativeTime(props.form.last_submission_at)) }}
      </span>
    </div>

    <AppDropdownMenu :items="menu" @select="(id) => emit('action', id as FormAction)">
      <AppIconButton
        icon="ellipsis"
        :label="t.actionsLabel(props.form.title)"
        size="sm"
        class="form-item__menu-button"
      />
    </AppDropdownMenu>
  </li>
</template>

<style scoped lang="scss">
.form-item {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: var(--space-2) var(--space-4);
  align-items: center;
  padding: var(--space-4);
  transition: background-color var(--duration-fast) var(--ease-out);

  &:hover {
    background: var(--color-surface-hover);
  }

  &__icon {
    display: grid;
    place-items: center;
    width: var(--control-height-md);
    height: var(--control-height-md);
    font-size: var(--font-size-lg);
    color: var(--color-text-muted);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__main {
    display: grid;
    gap: var(--space-1);
    min-width: 0;
  }

  &__title {
    overflow: hidden;
    font-weight: var(--font-weight-medium);
    color: var(--color-text);
    text-overflow: ellipsis;
    white-space: nowrap;
    text-decoration: none;
    border-radius: var(--radius-sm);

    // A linha inteira é clicável; o menu fica por cima (z-index).
    &::after {
      position: absolute;
      inset: 0;
      content: '';
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__meta {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__details {
    display: flex;
    flex-wrap: wrap;
    grid-column: 2 / -1;
    gap: var(--space-2) var(--space-4);
    align-items: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__menu-button {
    position: relative;
    z-index: 1;
    grid-row: 1;
    grid-column: 3;
  }

  @include up(md) {
    grid-template-columns: auto minmax(0, 1fr) auto auto;

    &__details {
      grid-column: 3;
      flex-wrap: nowrap;
      justify-content: flex-end;
    }

    &__count,
    &__last {
      white-space: nowrap;
    }

    &__menu-button {
      grid-column: 4;
    }
  }
}
</style>
