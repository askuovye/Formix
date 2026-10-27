<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { relativeTime } from '@/features/forms/relativeTime'
import { submissions } from '@/strings'
import type { SubmissionSummary } from '@/types/api'

/** Uma linha da lista: a primeira resposta identifica, o resto é contexto. */
const props = defineProps<{
  submission: SubmissionSummary
  selected: boolean
}>()

const emit = defineEmits<{
  select: []
}>()

const t = submissions

const title = computed(() => props.submission.preview[0]?.value ?? t.untitled)
const second = computed(() => props.submission.preview[1]?.value)
</script>

<template>
  <li>
    <button
      type="button"
      class="submission-item"
      :class="{ 'submission-item--selected': props.selected }"
      :aria-current="props.selected ? 'true' : undefined"
      @click="emit('select')"
    >
      <span class="submission-item__top">
        <span class="submission-item__title">{{ title }}</span>
        <time class="submission-item__time" :datetime="props.submission.created_at">
          {{ relativeTime(props.submission.created_at) }}
        </time>
      </span>
      <span v-if="second" class="submission-item__second">{{ second }}</span>
      <span class="submission-item__meta">
        <span class="submission-item__form">{{ props.submission.form.title }}</span>
        <span v-if="props.submission.files_count > 0" class="submission-item__tag">
          <AppIcon name="paperclip" />
          {{ t.files(props.submission.files_count) }}
        </span>
        <span v-if="props.submission.edited_at" class="submission-item__tag">
          <AppIcon name="pencil" />
          {{ t.edited }}
        </span>
      </span>
    </button>
  </li>
</template>

<style scoped lang="scss">
.submission-item {
  display: grid;
  gap: var(--space-1);
  inline-size: 100%;
  padding: var(--space-3) var(--space-4);
  font: inherit;
  color: var(--color-text);
  text-align: start;
  cursor: pointer;
  background: none;
  border: 0;
  border-inline-start: var(--border-width-strong) solid transparent;
  transition: background-color var(--duration-fast) var(--ease-out);

  &:hover {
    background: var(--color-surface-hover);
  }

  &:focus-visible {
    @include focus-ring;
  }

  &--selected,
  &--selected:hover {
    background: var(--color-surface-hover);
    border-inline-start-color: var(--color-primary);
  }

  &__top {
    display: flex;
    gap: var(--space-3);
    align-items: baseline;
    justify-content: space-between;
    min-width: 0;
  }

  &__title,
  &__second,
  &__form {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__title {
    font-weight: var(--font-weight-medium);
  }

  &__time {
    flex: none;
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__second {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-1) var(--space-3);
    align-items: center;
    min-width: 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
  }

  &__tag {
    display: inline-flex;
    gap: var(--space-1);
    align-items: center;
  }
}
</style>
