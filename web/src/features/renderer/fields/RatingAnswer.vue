<script setup lang="ts">
import { computed, ref, useId } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { renderer } from '@/strings'
import type { RatingField } from '@/types/schema'

import AnswerGroup from './AnswerGroup.vue'

/** Estrelas: botões de opção nativos (as setas do teclado funcionam) com a estrela como rótulo. */
const props = defineProps<{
  field: RatingField
  error?: string
}>()

const model = defineModel<number | null>({ default: null })

const name = useId()
const hovered = ref<number | null>(null)
const stars = computed(() => Array.from({ length: props.field.rating.max }, (_, i) => i + 1))
const lit = computed(() => hovered.value ?? model.value ?? 0)
</script>

<template>
  <AnswerGroup
    :label="props.field.label"
    :hint="props.field.help_text ?? undefined"
    :error="props.error"
    :required="props.field.required"
  >
    <div class="rating" @mouseleave="hovered = null">
      <label
        v-for="star in stars"
        :key="star"
        class="rating__star"
        :class="{ 'rating__star--lit': star <= lit }"
        @mouseenter="hovered = star"
      >
        <input
          v-model="model"
          type="radio"
          class="rating__input"
          :name="name"
          :value="star"
          :aria-invalid="props.error ? 'true' : undefined"
        />
        <AppIcon name="star" :filled="star <= lit" />
        <span class="rating__text">{{ renderer.rating(star, props.field.rating.max) }}</span>
      </label>
      <button
        v-if="model !== null && !props.field.required"
        type="button"
        class="rating__clear"
        @click="model = null"
      >
        {{ renderer.ratingClear }}
      </button>
    </div>
  </AnswerGroup>
</template>

<style scoped lang="scss">
.rating {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
  align-items: center;

  &__star {
    position: relative;
    display: grid;
    place-items: center;
    width: var(--control-height-md);
    height: var(--control-height-md);
    font-size: var(--font-size-2xl);
    color: var(--color-border-strong);
    cursor: pointer;
    border-radius: var(--radius-md);
    transition:
      color var(--duration-fast) var(--ease-out),
      transform var(--duration-fast) var(--ease-out);

    &:hover {
      transform: scale(1.1);
    }

    &:has(:focus-visible) {
      @include focus-ring;
    }

    &--lit {
      color: var(--color-warning);
    }
  }

  &__input,
  &__text {
    @include visually-hidden;
  }

  &__clear {
    padding: var(--space-1) var(--space-2);
    margin-inline-start: var(--space-2);
    font: inherit;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    cursor: pointer;
    background: none;
    border: 0;
    border-radius: var(--radius-sm);

    &:hover {
      color: var(--color-text);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }
}
</style>
