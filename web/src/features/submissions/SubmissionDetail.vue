<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { toApiError } from '@/api/client'
import { queryKeys } from '@/api/queryKeys'
import { deleteSubmission, fetchSubmission, fetchSubmissionPdf } from '@/api/submissions'
import { saveBlob } from '@/composables/saveBlob'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppConfirmDialog from '@/components/AppConfirmDialog.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { useToastStore } from '@/stores/useToastStore'
import { submissions } from '@/strings'

import { fileKind, iconForKind } from './fileKind'

/** Resposta completa: respostas, anexos (links assinados), histórico de edições e excluir. */
const props = defineProps<{
  id: string
}>()

const emit = defineEmits<{
  back: []
  deleted: []
  /** Filtrar pelas respostas desta pessoa (email de quem respondeu). */
  person: [email: string]
}>()

const t = submissions
const queryClient = useQueryClient()
const toasts = useToastStore()

const query = useQuery({
  queryKey: computed(() => queryKeys.submission(props.id)),
  queryFn: () => fetchSubmission(props.id),
})
const submission = computed(() => query.data.value)

const formatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
const format = (iso: string) => formatter.format(new Date(iso))

/** Imagem ampliada na janela (nome e link assinado). */
const viewing = ref<{ name: string; url: string } | null>(null)
const viewerOpen = computed({
  get: () => viewing.value !== null,
  set: (open) => {
    if (!open) viewing.value = null
  },
})

/** PDF gerado na hora pelo servidor; baixado como arquivo. */
const pdf = useMutation({
  mutationFn: () => fetchSubmissionPdf(props.id),
  onSuccess: ({ blob, filename }) => saveBlob(blob, filename),
  onError: () => toasts.show({ title: t.detail.pdfError, tone: 'danger' }),
})

const confirmOpen = ref(false)
const removal = useMutation({
  mutationFn: () => deleteSubmission(props.id),
  onSuccess: async () => {
    confirmOpen.value = false
    toasts.show({ title: t.deleted, tone: 'success' })
    queryClient.removeQueries({ queryKey: queryKeys.submission(props.id) })
    emit('deleted')
    // Listas de respostas, resultados e contadores dos formulários.
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: queryKeys.submissionLists }),
      queryClient.invalidateQueries({ queryKey: queryKeys.results }),
      queryClient.invalidateQueries({ queryKey: queryKeys.forms }),
    ])
  },
  onError: (error) =>
    toasts.show({ title: t.deleteError, description: toApiError(error).message, tone: 'danger' }),
})
</script>

<template>
  <section class="submission-detail" :aria-label="t.detail.label">
    <AppButton
      variant="ghost"
      size="sm"
      icon-left="arrow-left"
      class="submission-detail__back"
      @click="emit('back')"
    >
      {{ t.detail.back }}
    </AppButton>

    <p v-if="query.isPending.value" class="submission-detail__status" role="status">
      <AppSpinner />
      {{ t.loading }}
    </p>

    <div v-else-if="query.isError.value" class="submission-detail__error">
      <AppAlert tone="danger">{{ t.detail.loadError }}</AppAlert>
      <AppButton variant="secondary" icon-left="rotate-ccw" @click="query.refetch()">
        {{ t.retry }}
      </AppButton>
    </div>

    <template v-else-if="submission">
      <header class="submission-detail__header">
        <RouterLink
          :to="{ name: 'form-builder', params: { id: submission.form.id } }"
          class="submission-detail__form"
        >
          {{ submission.form.title }}
        </RouterLink>
        <p class="submission-detail__dates">
          {{ t.detail.receivedAt(format(submission.created_at)) }}
          <template v-if="submission.edited_at">
            · {{ t.detail.editedAt(format(submission.edited_at)) }}
          </template>
        </p>
      </header>

      <section class="submission-detail__section">
        <h3 class="submission-detail__heading">{{ t.detail.answers }}</h3>
        <dl class="submission-detail__answers">
          <div v-for="answer in submission.answers" :key="answer.field_id">
            <dt class="submission-detail__label">{{ answer.label }}</dt>
            <dd
              class="submission-detail__value"
              :class="{
                'submission-detail__value--empty': !answer.answered,
                'submission-detail__value--multiline': answer.multiline,
              }"
            >
              {{ answer.value }}
            </dd>
          </div>
        </dl>
      </section>

      <section v-if="submission.files.length > 0" class="submission-detail__section">
        <h3 class="submission-detail__heading">{{ t.detail.files }}</h3>
        <div
          v-for="group in submission.files"
          :key="group.field_id"
          class="submission-detail__group"
        >
          <p class="submission-detail__label">{{ group.label }}</p>
          <ul class="submission-detail__files">
            <li
              v-for="file in group.files"
              :key="file.name"
              class="submission-detail__file"
              :class="{
                'submission-detail__file--image': fileKind(file.name) === 'image' && file.url,
              }"
            >
              <!-- Imagem: prévia clicável que amplia; o resto, ícone do tipo. -->
              <button
                v-if="fileKind(file.name) === 'image' && file.url"
                type="button"
                class="submission-detail__preview"
                :aria-label="t.detail.view(file.name)"
                @click="viewing = { name: file.name, url: file.url }"
              >
                <img
                  :src="file.url"
                  alt=""
                  class="submission-detail__preview-image"
                  loading="lazy"
                  decoding="async"
                />
              </button>
              <AppIcon
                v-else
                :name="iconForKind(fileKind(file.name))"
                class="submission-detail__file-icon"
              />
              <span class="submission-detail__file-name">{{ file.name }}</span>
              <span class="submission-detail__file-size">{{ file.size }}</span>
              <a
                v-if="file.url"
                :href="file.url"
                class="submission-detail__download"
                :aria-label="t.detail.download(file.name)"
                download
              >
                <AppIcon name="download" />
              </a>
            </li>
          </ul>
        </div>
      </section>

      <details v-if="submission.revisions.length > 0" class="submission-detail__history">
        <summary class="submission-detail__summary">
          <AppIcon name="chevron-right" class="submission-detail__chevron" />
          {{ t.detail.history(submission.revisions.length) }}
        </summary>
        <article
          v-for="revision in submission.revisions"
          :key="revision.replaced_at"
          class="submission-detail__revision"
        >
          <h4 class="submission-detail__revision-title">
            {{ t.detail.replacedAt(format(revision.replaced_at)) }}
          </h4>
          <dl class="submission-detail__answers">
            <div v-for="answer in revision.answers" :key="answer.field_id">
              <dt class="submission-detail__label">{{ answer.label }}</dt>
              <dd
                class="submission-detail__value"
                :class="{
                  'submission-detail__value--empty': !answer.answered,
                  'submission-detail__value--multiline': answer.multiline,
                }"
              >
                {{ answer.value }}
              </dd>
            </div>
          </dl>
          <template v-if="revision.replaced_files.length > 0">
            <p class="submission-detail__label">{{ t.detail.replacedFiles }}</p>
            <ul class="submission-detail__replaced">
              <li v-for="file in revision.replaced_files" :key="file.name">{{ file.name }}</li>
            </ul>
          </template>
        </article>
      </details>

      <footer class="submission-detail__footer">
        <AppButton
          variant="secondary"
          icon-left="file-text"
          size="sm"
          :loading="pdf.isPending.value"
          @click="pdf.mutate()"
        >
          {{ t.detail.pdf }}
        </AppButton>
        <AppButton
          v-if="submission.respondent_email"
          variant="secondary"
          icon-left="user"
          size="sm"
          @click="emit('person', submission.respondent_email)"
        >
          {{ t.detail.person }}
        </AppButton>
        <AppButton variant="danger" icon-left="trash-2" size="sm" @click="confirmOpen = true">
          {{ t.detail.remove }}
        </AppButton>
      </footer>
    </template>

    <AppDialog v-model:open="viewerOpen" :title="viewing?.name ?? ''" size="full">
      <div class="submission-detail__viewer">
        <img v-if="viewing" :src="viewing.url" alt="" class="submission-detail__viewer-image" />
      </div>
      <template #footer>
        <AppButton v-if="viewing" variant="secondary" icon-left="download" :href="viewing.url">
          {{ t.detail.downloadFile }}
        </AppButton>
      </template>
    </AppDialog>

    <AppConfirmDialog
      v-model:open="confirmOpen"
      :title="t.confirmDelete.title"
      :description="t.confirmDelete.text"
      :confirm-label="t.confirmDelete.confirm"
      :cancel-label="t.confirmDelete.cancel"
      tone="danger"
      :loading="removal.isPending.value"
      @confirm="removal.mutate()"
    />
  </section>
</template>

<style scoped lang="scss">
.submission-detail {
  display: grid;
  gap: var(--space-6);
  align-content: start;
  padding: var(--space-4);

  @include up(lg) {
    padding: var(--space-6);
  }

  &__back {
    justify-self: start;

    @include up(lg) {
      display: none;
    }
  }

  &__status {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    justify-content: center;
    padding-block: var(--space-12);
    color: var(--color-text-muted);
  }

  &__error {
    display: grid;
    gap: var(--space-4);
    justify-items: start;
  }

  &__header {
    display: grid;
    gap: var(--space-1);
  }

  &__form {
    justify-self: start;
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text);
    text-decoration: none;
    border-radius: var(--radius-sm);

    &:hover {
      text-decoration: underline;
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__dates {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__section {
    display: grid;
    gap: var(--space-3);
  }

  &__heading {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__answers {
    display: grid;
    gap: var(--space-4);
    margin: 0;
  }

  &__label {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
  }

  &__value {
    margin: 0;
    margin-block-start: var(--space-1);
    overflow-wrap: anywhere;

    &--multiline {
      white-space: pre-line;
    }

    &--empty {
      font-style: italic;
      color: var(--color-text-subtle);
    }
  }

  &__group {
    display: grid;
    gap: var(--space-2);
  }

  &__files {
    display: grid;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__file {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-2) var(--space-3);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__file-icon {
    flex: none;
    color: var(--color-text-muted);
  }

  // Imagem: prévia em cima ocupando a largura, nome e download embaixo.
  &__file--image {
    flex-wrap: wrap;
    padding-block-start: var(--space-3);
  }

  &__preview {
    display: grid;
    flex-basis: 100%;
    place-items: center;
    block-size: var(--attachment-preview-height);
    padding: 0;
    overflow: hidden;
    cursor: zoom-in;
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-sm);

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__preview-image {
    max-inline-size: 100%;
    max-block-size: 100%;
    object-fit: contain;
    transition: transform var(--duration-base) var(--ease-out);
  }

  &__preview:hover &__preview-image {
    transform: scale(1.03);
  }

  &__viewer {
    display: grid;
    // Sem minmax a coluna cresce até a largura natural da imagem e vaza no celular.
    grid-template-columns: minmax(0, 1fr);
    place-items: center;
    min-block-size: 100%;
  }

  &__viewer-image {
    max-inline-size: 100%;
    max-block-size: 70dvh;
    object-fit: contain;
    border-radius: var(--radius-md);
  }

  &__file-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    font-size: var(--font-size-sm);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__file-size {
    flex: none;
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__download {
    display: grid;
    flex: none;
    place-items: center;
    inline-size: var(--control-height-sm);
    block-size: var(--control-height-sm);
    color: var(--color-text-muted);
    border-radius: var(--radius-md);
    transition:
      background-color var(--duration-fast) var(--ease-out),
      color var(--duration-fast) var(--ease-out);

    &:hover {
      color: var(--color-text);
      background: var(--color-surface-hover);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__history {
    border-block-start: var(--border-width) solid var(--color-border);
    padding-block-start: var(--space-4);
  }

  &__summary {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    list-style: none;
    border-radius: var(--radius-sm);

    &::-webkit-details-marker {
      display: none;
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  &__chevron {
    color: var(--color-text-muted);
    transition: transform var(--duration-fast) var(--ease-out);
  }

  &__history[open] &__chevron {
    transform: rotate(90deg);
  }

  &__revision {
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
    margin-block-start: var(--space-3);
    background: var(--color-surface-raised);
    border-radius: var(--radius-md);
  }

  &__revision-title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__replaced {
    padding-inline-start: var(--space-4);
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__footer {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
    justify-content: flex-end;
    padding-block-start: var(--space-4);
    border-block-start: var(--border-width) solid var(--color-border);
  }
}
</style>
