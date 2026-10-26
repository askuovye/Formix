<script setup lang="ts">
import { computed, ref } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppConfirmDialog from '@/components/AppConfirmDialog.vue'
import AppDropdownMenu, { type DropdownMenuEntry } from '@/components/AppDropdownMenu.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import type { IconName } from '@/components/icons/iconNames'
import { useFormActions } from '@/features/forms/useFormActions'
import { builder, common } from '@/strings'
import type { Form } from '@/types/api'

/** Botão de publicar (ou reabrir) e menu com link e "Fechar formulário". */
const props = defineProps<{
  form: Form
  publishing: boolean
  closing: boolean
}>()

const emit = defineEmits<{
  publish: []
  preview: []
  /** Confirmado pelo dono; `done` fecha a confirmação quando terminar. */
  close: [done: (closed: boolean) => void]
}>()

const t = builder.publish
const { copyLink } = useFormActions()
const confirmOpen = ref(false)

const button = computed<{ label: string; icon: IconName; disabled: boolean }>(() => {
  const { status, has_unpublished_changes: changed } = props.form

  if (status === 'closed') return { label: t.reopen, icon: 'send', disabled: false }
  if (status === 'published' && !changed) {
    return { label: t.published, icon: 'check', disabled: true }
  }
  if (status === 'published') return { label: t.publishChanges, icon: 'send', disabled: false }

  return { label: t.publish, icon: 'send', disabled: false }
})

const hasLink = computed(() => props.form.published_version > 0)

const menuItems = computed<DropdownMenuEntry[]>(() => [
  { type: 'item', id: 'preview', label: t.preview, icon: 'eye' },
  { type: 'separator' },
  { type: 'item', id: 'copy', label: t.copyLink, icon: 'link', disabled: !hasLink.value },
  {
    type: 'item',
    id: 'open',
    label: t.openLink,
    icon: 'external-link',
    disabled: props.form.status !== 'published',
  },
  { type: 'separator' },
  {
    type: 'item',
    id: 'close',
    label: t.close,
    icon: 'lock',
    danger: true,
    disabled: props.form.status !== 'published',
  },
])

function onSelect(id: string): void {
  if (id === 'preview') emit('preview')
  else if (id === 'copy') void copyLink(props.form.public_url)
  else if (id === 'open') window.open(props.form.public_url, '_blank', 'noopener')
  else if (id === 'close') confirmOpen.value = true
}

function confirmClose(): void {
  emit('close', (closed) => {
    if (closed) confirmOpen.value = false
  })
}
</script>

<template>
  <div class="publish-control">
    <AppButton
      size="sm"
      :icon-left="button.icon"
      :loading="props.publishing"
      :disabled="button.disabled"
      :aria-label="button.label"
      class="publish-control__button"
      @click="emit('publish')"
    >
      <span class="publish-control__label">{{ button.label }}</span>
    </AppButton>

    <AppDropdownMenu :items="menuItems" @select="onSelect">
      <AppIconButton icon="ellipsis" :label="t.more" />
    </AppDropdownMenu>

    <AppConfirmDialog
      v-model:open="confirmOpen"
      :title="t.closeConfirmTitle"
      :description="t.closeConfirmText"
      :confirm-label="t.closeConfirm"
      :cancel-label="common.cancel"
      tone="danger"
      :loading="props.closing"
      @confirm="confirmClose"
    />
  </div>
</template>

<style scoped lang="scss">
.publish-control {
  display: flex;
  gap: var(--space-1);
  align-items: center;

  // No celular só o ícone; o nome fica no aria-label.
  &__label {
    display: none;

    @include up(sm) {
      display: inline;
    }
  }
}
</style>
