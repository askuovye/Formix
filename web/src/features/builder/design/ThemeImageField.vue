<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'

import { toApiError } from '@/api/client'
import { uploadFormAsset, type FormAssetKind } from '@/api/forms'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import { builder } from '@/strings'

import { useBuilderStore } from '../useBuilderStore'

/**
 * Capa ou logo do tema: envia a imagem (imutável no servidor) e aponta o tema
 * para ela. Remover só tira do tema; o servidor apaga quando ninguém mais usa.
 */
const props = defineProps<{
  kind: FormAssetKind
}>()

/** Espelho do back-end (FormAssetController): tipos e tamanho aceitos. */
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp']
const MAX_BYTES = 5 * 1024 * 1024

const store = useBuilderStore()
const t = builder.design
const input = useTemplateRef<HTMLInputElement>('input')

const uploading = ref(false)
const error = ref<string>()

const current = computed(() => store.schema.theme?.[props.kind] ?? null)
const label = computed(() => (props.kind === 'cover' ? t.cover : t.logo))
const hint = computed(() => (props.kind === 'cover' ? t.coverHint : t.logoHint))

function pick(): void {
  input.value?.click()
}

async function onFile(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  target.value = ''
  if (!file || !store.formId) return

  error.value = undefined
  if (!ACCEPTED.includes(file.type)) return void (error.value = t.errors.type)
  if (file.size > MAX_BYTES) return void (error.value = t.errors.size)

  uploading.value = true
  try {
    const asset = await uploadFormAsset(store.formId, file, props.kind)

    store.updateTheme((draft) => {
      if (props.kind === 'cover') {
        draft.cover = {
          asset_id: asset.id,
          fit: draft.cover?.fit ?? 'cover',
          overlay_opacity: draft.cover?.overlay_opacity ?? 0,
          url: asset.url,
        }
      } else {
        draft.logo = { asset_id: asset.id, url: asset.url }
      }
    }, props.kind)
  } catch (caught) {
    const apiError = toApiError(caught)
    error.value =
      apiError.status === 409
        ? t.errors.limit
        : apiError.status === 422
          ? (Object.values(apiError.firstFieldErrors)[0] ?? t.errors.upload)
          : t.errors.upload
  } finally {
    uploading.value = false
  }
}

function remove(): void {
  store.updateTheme((draft) => {
    draft[props.kind] = null
  }, props.kind)
}
</script>

<template>
  <div class="image-field">
    <p class="image-field__label">{{ label }}</p>

    <div v-if="current?.url" class="image-field__preview" :class="`image-field__preview--${kind}`">
      <img :src="current.url" alt="" class="image-field__image" />
    </div>

    <div class="image-field__actions">
      <AppButton
        size="sm"
        variant="secondary"
        icon-left="upload"
        :loading="uploading"
        @click="pick"
      >
        {{ current ? t.replace : t.upload }}
      </AppButton>
      <AppButton v-if="current" size="sm" variant="ghost" icon-left="trash-2" @click="remove">
        {{ t.remove }}
      </AppButton>
    </div>
    <input
      ref="input"
      type="file"
      class="image-field__input"
      :accept="ACCEPTED.join(',')"
      tabindex="-1"
      aria-hidden="true"
      @change="onFile"
    />

    <p v-if="error" class="image-field__error" role="alert">
      <AppIcon name="circle-alert" />
      {{ error }}
    </p>
    <p v-else class="image-field__hint">{{ hint }}</p>
  </div>
</template>

<style scoped lang="scss">
.image-field {
  display: grid;
  gap: var(--space-2);

  &__label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__preview {
    display: grid;
    place-items: center;
    overflow: hidden;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);

    &--cover {
      block-size: calc(var(--space-16) + var(--space-8));
    }

    &--logo {
      block-size: var(--space-16);
      padding: var(--space-2);
    }
  }

  &__image {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
  }

  &__preview--logo &__image {
    object-fit: contain;
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
  }

  &__input {
    @include visually-hidden;
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__error {
    display: flex;
    gap: var(--space-1);
    align-items: center;
    font-size: var(--font-size-xs);
    color: var(--color-danger);
  }
}
</style>
