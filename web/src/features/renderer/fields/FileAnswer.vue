<script setup lang="ts">
import type { Uppy } from '@uppy/core'
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, useId } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import type { IconName } from '@/components/icons/iconNames'
import {
  FILE_CATEGORIES,
  acceptAttribute,
  acceptedTypesLabel,
  fileRejection,
  formatFileSize,
} from '@/features/validation/files'
import { renderer, validation } from '@/strings'
import type { FileField } from '@/types/schema'

import AnswerGroup from './AnswerGroup.vue'

/**
 * Campo de arquivo. O núcleo do Uppy (carregado só quando o formulário tem
 * este campo) guarda a lista; a área de soltar e a lista são nossas, no tema
 * do dono. Nada sobe aqui: os arquivos vão junto com a resposta, e o servidor
 * revalida tudo pelo conteúdo.
 */
const props = defineProps<{
  field: FileField
  error?: string
}>()

const model = defineModel<File[]>({ default: () => [] })

interface Entry {
  id: string
  name: string
  size: number
  icon: IconName
  /** Miniatura (só imagens), via URL local do próprio arquivo. */
  preview?: string
}

const t = renderer.file
const inputId = useId()
const uppy = shallowRef<Uppy>()
const entries = ref<Entry[]>([])
const rejection = ref<string>()
const announcement = ref('')
const dragDepth = ref(0)
const previews = new Map<string, string>()

const single = computed(() => props.field.file.max_files === 1)
const accept = computed(() => acceptAttribute(props.field))
const limits = computed(() =>
  t.limits(
    acceptedTypesLabel(props.field),
    props.field.file.max_size_mb,
    props.field.file.max_files,
  ),
)
const full = computed(() => !single.value && entries.value.length >= props.field.file.max_files)

function iconFor(file: File): IconName {
  if (file.type.startsWith('image/')) return 'image'
  if (FILE_CATEGORIES.spreadsheet.mimeTypes.includes(file.type)) return 'file-spreadsheet'
  if (file.type === '' || file.type === 'application/octet-stream') return 'file'
  return 'file-text'
}

function sync(): void {
  const files = uppy.value?.getFiles() ?? []
  entries.value = files.map((item) => {
    const file = item.data as File
    if (file.type.startsWith('image/') && !previews.has(item.id)) {
      previews.set(item.id, URL.createObjectURL(file))
    }
    return {
      id: item.id,
      name: file.name,
      size: file.size,
      icon: iconFor(file),
      preview: previews.get(item.id),
    }
  })
  model.value = files.map((item) => item.data as File)
}

function forget(id: string): void {
  const url = previews.get(id)
  if (url) URL.revokeObjectURL(url)
  previews.delete(id)
}

/**
 * Confere cada arquivo (mesmas regras do servidor) antes de entregar ao Uppy.
 * Com limite de 1, o arquivo novo substitui o atual.
 */
function add(list: FileList | File[]): void {
  const instance = uppy.value
  if (!instance || list.length === 0) return

  if (single.value) {
    instance.getFiles().forEach((item) => {
      forget(item.id)
      instance.removeFile(item.id)
    })
  }

  let count = instance.getFiles().length
  let message: string | undefined
  const accepted: File[] = []
  for (const file of single.value ? [...list].slice(0, 1) : [...list]) {
    const reason = fileRejection(props.field, file, count)
    if (reason) {
      message ??= reason
      continue
    }
    const duplicate = instance
      .getFiles()
      .some((item) => item.name === file.name && item.size === file.size)
    if (duplicate) {
      message ??= validation.files.duplicate(file.name)
      continue
    }
    accepted.push(file)
    count += 1
  }

  instance.addFiles(
    accepted.map((file) => ({ name: file.name, type: file.type, data: file, source: 'Local' })),
  )
  rejection.value = message
  sync()
  if (accepted.length > 0) announcement.value = accepted.map((file) => t.added(file.name)).join(' ')
}

function remove(entry: Entry): void {
  forget(entry.id)
  uppy.value?.removeFile(entry.id)
  rejection.value = undefined
  sync()
  announcement.value = t.removed(entry.name)
}

function onChange(event: Event): void {
  const input = event.target as HTMLInputElement
  if (input.files) add(input.files)
  input.value = ''
}

function onDrop(event: DragEvent): void {
  dragDepth.value = 0
  if (event.dataTransfer?.files) add(event.dataTransfer.files)
}

onMounted(async () => {
  const { default: UppyCore } = await import('@uppy/core')
  // Os limites já são conferidos em `add`; os do Uppy ficam como rede de segurança.
  uppy.value = new UppyCore({
    autoProceed: false,
    restrictions: {
      maxNumberOfFiles: props.field.file.max_files,
      maxFileSize: props.field.file.max_size_mb * 1024 * 1024,
    },
  })
  if (model.value.length > 0) add(model.value)
})

onBeforeUnmount(() => {
  previews.forEach((url) => URL.revokeObjectURL(url))
  previews.clear()
  uppy.value?.destroy()
})
</script>

<template>
  <AnswerGroup
    :label="props.field.label"
    :hint="props.field.help_text ?? undefined"
    :error="rejection ?? props.error"
    :required="props.field.required"
  >
    <label
      v-if="!full"
      :for="inputId"
      class="file-answer__drop"
      :class="{
        'file-answer__drop--over': dragDepth > 0,
        'file-answer__drop--loading': !uppy,
      }"
      @dragenter.prevent="dragDepth += 1"
      @dragover.prevent
      @dragleave="dragDepth = Math.max(0, dragDepth - 1)"
      @drop.prevent="onDrop"
    >
      <AppIcon name="upload" class="file-answer__icon" />
      <!-- Com mouse: arrastar ou escolher; no toque, só escolher. -->
      <span class="file-answer__cta file-answer__cta--pointer">
        {{ single ? t.dropOne : t.drop }}
        <span class="file-answer__choose">{{ t.choose }}</span>
      </span>
      <span class="file-answer__cta file-answer__cta--touch">
        <span class="file-answer__choose">{{ single ? t.tapOne : t.tap }}</span>
      </span>
      <span class="file-answer__limits">{{ uppy ? limits : t.loading }}</span>
      <input
        :id="inputId"
        type="file"
        class="file-answer__input"
        :accept="accept"
        :multiple="!single"
        :disabled="!uppy"
        @change="onChange"
      />
    </label>

    <ul v-if="entries.length > 0" class="file-answer__list">
      <li v-for="entry in entries" :key="entry.id" class="file-answer__item">
        <img
          v-if="entry.preview"
          :src="entry.preview"
          alt=""
          class="file-answer__thumb"
          decoding="async"
        />
        <span v-else class="file-answer__thumb file-answer__thumb--icon">
          <AppIcon :name="entry.icon" />
        </span>
        <span class="file-answer__meta">
          <span class="file-answer__name">{{ entry.name }}</span>
          <span class="file-answer__size">{{ formatFileSize(entry.size) }}</span>
        </span>
        <AppIconButton icon="x" size="sm" :label="t.remove(entry.name)" @click="remove(entry)" />
      </li>
    </ul>

    <p class="file-answer__status" role="status">{{ announcement }}</p>
  </AnswerGroup>
</template>

<style scoped lang="scss">
.file-answer {
  &__drop {
    display: grid;
    gap: var(--space-1);
    justify-items: center;
    padding: var(--space-6) var(--space-4);
    text-align: center;
    cursor: pointer;
    background: var(--color-surface);
    border: var(--border-width-strong) dashed var(--color-border-strong);
    border-radius: var(--radius-md);
    transition:
      border-color var(--duration-fast) var(--ease-out),
      background-color var(--duration-fast) var(--ease-out);

    &:hover,
    &--over {
      background: var(--color-surface-hover);
      border-color: var(--color-primary);
    }

    &:focus-within {
      @include focus-ring;
    }

    &--loading {
      cursor: progress;
    }
  }

  &__icon {
    font-size: var(--font-size-xl);
    color: var(--color-text-muted);
  }

  &__cta {
    font-size: var(--font-size-sm);

    &--touch {
      display: none;
    }

    @media (hover: none) {
      &--pointer {
        display: none;
      }

      &--touch {
        display: inline;
      }
    }
  }

  &__choose {
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary);
    text-decoration: underline;
    text-underline-offset: var(--distance-sm);
  }

  &__limits {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__input {
    @include visually-hidden;
  }

  &__list {
    display: grid;
    gap: var(--space-2);
    padding: 0;
    margin: 0;
    list-style: none;
  }

  &__item {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-2);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__thumb {
    flex: none;
    inline-size: var(--control-height-md);
    block-size: var(--control-height-md);
    object-fit: cover;
    border-radius: var(--radius-sm);

    &--icon {
      display: grid;
      place-items: center;
      font-size: var(--font-size-lg);
      color: var(--color-text-muted);
      background: var(--color-surface-hover);
    }
  }

  &__meta {
    display: grid;
    flex: 1;
    min-width: 0;
  }

  &__name {
    overflow: hidden;
    font-size: var(--font-size-sm);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__size {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__status {
    @include visually-hidden;
  }
}
</style>
