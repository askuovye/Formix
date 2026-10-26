<script setup lang="ts">
import { computed } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { useFormActions } from '@/features/forms/useFormActions'
import { builder } from '@/strings'

import { DETAILS_LIMITS, type FormDetails } from './details'
import { useBuilderForm } from './useBuilderForm'
import { useFormDetailsStore } from './useFormDetailsStore'

/** Aba "Formulário": título, descrição, endereço e link público. */
const store = useFormDetailsStore()
const form = useBuilderForm()
const { copyLink } = useFormActions()
const t = builder.details

function field<K extends 'title' | 'description' | 'slug'>(key: K) {
  return computed<FormDetails[K]>({
    get: () => store.details?.[key] ?? '',
    set: (value) => store.update((draft) => (draft[key] = value), key),
  })
}

const title = field('title')
const description = field('description')
const slug = field('slug')

/** Link com o endereço em edição (o servidor só troca o último trecho). */
const previewUrl = computed(() => {
  const url = form.data.value?.public_url ?? ''

  return url.replace(/[^/]*$/, slug.value)
})

const published = computed(() => (form.data.value?.published_version ?? 0) > 0)
</script>

<template>
  <div v-if="store.details" class="form-details">
    <AppTextField
      v-model="title"
      :label="t.title"
      :maxlength="DETAILS_LIMITS.titleMax"
      :error="store.errors.title"
      required
    />
    <AppTextarea
      v-model="description"
      :label="t.description"
      :hint="t.descriptionHint"
      :rows="4"
      :maxlength="DETAILS_LIMITS.descriptionMax"
      :error="store.errors.description"
    />
    <AppTextField
      v-model.trim="slug"
      :label="t.slug"
      :hint="store.slugEditable ? t.slugHint(previewUrl) : t.slugLocked"
      :maxlength="DETAILS_LIMITS.slug.max"
      :error="store.errors.slug"
      :disabled="!store.slugEditable"
    />

    <section v-if="published && form.data.value" class="form-details__link">
      <h3 class="form-details__heading">{{ t.publicLink }}</h3>
      <p class="form-details__url">{{ form.data.value.public_url }}</p>
      <div class="form-details__actions">
        <AppButton
          variant="secondary"
          size="sm"
          icon-left="link"
          @click="copyLink(form.data.value.public_url)"
        >
          {{ t.copyLink }}
        </AppButton>
        <AppButton
          :href="form.data.value.public_url"
          variant="ghost"
          size="sm"
          icon-left="external-link"
        >
          {{ t.openLink }}
        </AppButton>
      </div>
    </section>
  </div>
</template>

<style scoped lang="scss">
.form-details {
  display: grid;
  gap: var(--space-4);

  &__link {
    display: grid;
    gap: var(--space-2);
    padding-block-start: var(--space-4);
    border-block-start: var(--border-width) solid var(--color-border);
  }

  &__heading {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__url {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    overflow-wrap: anywhere;
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
  }
}
</style>
