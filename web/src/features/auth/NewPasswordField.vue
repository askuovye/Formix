<script setup lang="ts">
import { computed } from 'vue'

import AppTextField from '@/components/AppTextField.vue'
import { auth } from '@/strings'

import { passwordStrength } from './passwordStrength'

const model = defineModel<string>({ required: true })

const props = defineProps<{
  label: string
  error?: string
}>()

const t = auth.strength
const level = computed(() => passwordStrength(model.value))
const modifier = computed(() => ['empty', 'weak', 'fair', 'good', 'strong'][level.value])
</script>

<template>
  <div class="new-password">
    <AppTextField
      v-model="model"
      type="password"
      :label="props.label"
      :hint="auth.fields.passwordHint"
      autocomplete="new-password"
      :error="props.error"
      required
    />
    <div class="strength" :class="`strength--${modifier}`">
      <div class="strength__bar" aria-hidden="true">
        <span
          v-for="n in 4"
          :key="n"
          class="strength__segment"
          :class="{ 'strength__segment--on': n <= level }"
        />
      </div>
      <!-- Anuncia só quando o nível muda (o texto não muda a cada tecla). -->
      <p class="strength__label" aria-live="polite">
        <template v-if="level > 0">
          {{ t.label }} <strong class="strength__level">{{ t.levels[level] }}</strong>
        </template>
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.new-password {
  display: grid;
  gap: var(--space-2);
}

.strength {
  --strength-color: var(--color-border-strong);

  display: grid;
  gap: var(--space-1);

  &--weak {
    --strength-color: var(--color-danger);
  }

  &--fair {
    --strength-color: var(--color-warning);
  }

  &--good,
  &--strong {
    --strength-color: var(--color-success);
  }

  &__bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-1);
  }

  &__segment {
    height: var(--space-1);
    background-color: var(--color-border);
    border-radius: var(--radius-full);
    transition: background-color var(--duration-base) var(--ease-out);

    &--on {
      background-color: var(--strength-color);
    }
  }

  &__label {
    min-height: calc(var(--font-size-xs) * var(--line-height-base));
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__level {
    font-weight: var(--font-weight-semibold);
    color: var(--strength-color);
  }
}
</style>
