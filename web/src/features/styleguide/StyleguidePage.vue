<script setup lang="ts">
import { ref } from 'vue'

import AppBadge from '@/components/AppBadge.vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppCheckbox from '@/components/AppCheckbox.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import AppRadioGroup from '@/components/AppRadioGroup.vue'
import AppSwitch from '@/components/AppSwitch.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { ICON_NAMES } from '@/components/icons/iconNames'
import { BRAND_NAME } from '@/config/brand'
import { useThemeStore, type ThemePreference } from '@/stores/useThemeStore'
import { styleguide as t } from '@/strings'

const theme = useThemeStore()
const themeOptions: { value: ThemePreference; label: string }[] = [
  { value: 'system', label: t.theme.system },
  { value: 'light', label: t.theme.light },
  { value: 'dark', label: t.theme.dark },
]

const colorTokens = [
  'bg',
  'surface',
  'surface-raised',
  'surface-hover',
  'border',
  'border-strong',
  'text',
  'text-muted',
  'text-subtle',
  'primary',
  'primary-hover',
  'selected',
  'success',
  'success-soft',
  'warning',
  'warning-soft',
  'danger',
  'danger-soft',
] as const

const fontSizes = ['4xl', '3xl', '2xl', 'xl', 'lg', 'md', 'sm', 'xs'] as const
const fontWeights = [
  { token: 'regular', label: t.typography.weights.regular },
  { token: 'medium', label: t.typography.weights.medium },
  { token: 'semibold', label: t.typography.weights.semibold },
  { token: 'bold', label: t.typography.weights.bold },
] as const

const title = ref('')
const email = ref('nome@exemplo')
const search = ref('')
const description = ref('')
const acceptCopy = ref(true)
const objective = ref<string>()
const objectiveWithError = ref<string>()
const required = ref(true)
const showDescription = ref(false)
</script>

<template>
  <main class="styleguide">
    <header class="styleguide__header">
      <h1 class="styleguide__title">{{ BRAND_NAME }} · {{ t.title }}</h1>
      <p class="styleguide__intro">{{ t.intro }}</p>
    </header>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.theme }}</h2>
      <div class="styleguide__row">
        <AppButton
          v-for="option in themeOptions"
          :key="option.value"
          :variant="theme.preference === option.value ? 'primary' : 'secondary'"
          size="sm"
          :aria-pressed="theme.preference === option.value"
          @click="theme.setPreference(option.value)"
        >
          {{ option.label }}
        </AppButton>
        <span class="styleguide__muted">{{ t.theme.current }} {{ theme.resolved }}</span>
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.colors }}</h2>
      <ul class="styleguide__swatches">
        <li v-for="token in colorTokens" :key="token" class="styleguide__swatch">
          <span class="styleguide__swatch-color" :style="{ background: `var(--color-${token})` }" />
          <code class="styleguide__code">--color-{{ token }}</code>
        </li>
      </ul>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.typography }}</h2>
      <div class="styleguide__stack">
        <p
          v-for="size in fontSizes"
          :key="size"
          class="styleguide__type"
          :style="{ fontSize: `var(--font-size-${size})` }"
        >
          <code class="styleguide__code">{{ size }}</code>
          {{ t.typography.sample }}
        </p>
      </div>
      <div class="styleguide__row">
        <span
          v-for="weight in fontWeights"
          :key="weight.token"
          :style="{ fontWeight: `var(--font-weight-${weight.token})` }"
        >
          {{ weight.label }}
        </span>
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.icons }}</h2>
      <ul class="styleguide__icons">
        <li v-for="name in ICON_NAMES" :key="name" class="styleguide__icon">
          <AppIcon :name="name" class="styleguide__icon-glyph" />
          <code class="styleguide__code">{{ name }}</code>
        </li>
      </ul>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.buttons }}</h2>
      <div class="styleguide__row">
        <AppButton>{{ t.buttons.primary }}</AppButton>
        <AppButton variant="secondary">{{ t.buttons.secondary }}</AppButton>
        <AppButton variant="ghost">{{ t.buttons.ghost }}</AppButton>
        <AppButton variant="danger" icon-left="trash-2">{{ t.buttons.danger }}</AppButton>
      </div>
      <div class="styleguide__row">
        <AppButton icon-left="plus">{{ t.buttons.withIcon }}</AppButton>
        <AppButton variant="secondary" icon-right="chevron-right">{{
          t.buttons.secondary
        }}</AppButton>
        <AppButton loading>{{ t.buttons.loading }}</AppButton>
        <AppButton disabled>{{ t.buttons.disabled }}</AppButton>
      </div>
      <div class="styleguide__row">
        <AppButton size="sm">{{ t.buttons.small }}</AppButton>
        <AppButton size="sm" variant="secondary">{{ t.buttons.small }}</AppButton>
        <AppIconButton icon="ellipsis" :label="t.buttons.iconLabels.more" />
        <AppIconButton icon="x" :label="t.buttons.iconLabels.close" size="sm" />
        <AppIconButton icon="copy" :label="t.buttons.iconLabels.copy" variant="secondary" />
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.fields }}</h2>
      <div class="styleguide__grid">
        <AppTextField
          v-model="title"
          :label="t.fields.title"
          :placeholder="t.fields.titlePlaceholder"
          :hint="t.fields.titleHint"
          required
        />
        <AppTextField
          v-model="email"
          type="email"
          :label="t.fields.email"
          :error="t.fields.emailError"
        />
        <AppTextField v-model="search" type="search" :label="t.fields.search" icon-left="search" />
        <AppTextField :label="t.fields.disabled" disabled />
        <AppTextarea
          v-model="description"
          :label="t.fields.description"
          :placeholder="t.fields.descriptionPlaceholder"
        />
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.choices }}</h2>
      <div class="styleguide__grid">
        <div class="styleguide__stack">
          <AppCheckbox
            v-model="acceptCopy"
            :label="t.choices.checkbox"
            :hint="t.choices.checkboxHint"
          />
          <AppCheckbox :label="t.choices.checkboxDisabled" disabled />
          <AppSwitch v-model="required" :label="t.choices.switchRequired" />
          <AppSwitch v-model="showDescription" :label="t.choices.switchDescription" />
          <AppSwitch :label="t.choices.switchDisabled" disabled />
        </div>
        <AppRadioGroup
          v-model="objective"
          :label="t.choices.radioLabel"
          :options="t.choices.radioOptions"
          required
        />
        <AppRadioGroup
          v-model="objectiveWithError"
          :label="t.choices.radioLabel"
          :options="t.choices.radioOptions"
          :error="t.choices.radioError"
        />
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.badges }}</h2>
      <div class="styleguide__row">
        <AppBadge tone="success">{{ t.badges.active }}</AppBadge>
        <AppBadge tone="warning">{{ t.badges.draft }}</AppBadge>
        <AppBadge>{{ t.badges.closed }}</AppBadge>
        <AppBadge tone="danger">{{ t.badges.error }}</AppBadge>
      </div>
    </section>

    <section class="styleguide__section">
      <h2 class="styleguide__heading">{{ t.sections.cards }}</h2>
      <div class="styleguide__grid">
        <AppCard>
          <p class="styleguide__card-title">{{ t.cards.title }}</p>
          <p class="styleguide__muted">{{ t.cards.body }}</p>
        </AppCard>
        <AppCard interactive padding="lg">
          <p class="styleguide__card-title">{{ t.cards.title }}</p>
          <p class="styleguide__muted">{{ t.cards.body }}</p>
        </AppCard>
      </div>
    </section>
  </main>
</template>

<style scoped lang="scss">
.styleguide {
  display: grid;
  gap: var(--space-12);
  max-width: var(--layout-width-lg);
  padding: var(--space-8) var(--space-4);
  margin-inline: auto;

  @include up(md) {
    padding-inline: var(--space-8);
  }

  &__title {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
  }

  &__intro,
  &__muted {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__intro {
    margin-block-start: var(--space-2);
  }

  &__section {
    display: grid;
    gap: var(--space-4);
  }

  &__heading {
    padding-block-end: var(--space-2);
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    border-block-end: var(--border-width) solid var(--color-border);
  }

  &__row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: center;
  }

  &__stack {
    display: grid;
    gap: var(--space-4);
    align-content: start;
  }

  &__grid {
    display: grid;
    gap: var(--space-6);

    @include up(md) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @include up(lg) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  &__swatches,
  &__icons {
    display: grid;
    gap: var(--space-3);
    padding: 0;
    list-style: none;
  }

  &__swatches {
    grid-template-columns: repeat(auto-fill, minmax(var(--layout-icon-cell), 1fr));
  }

  &__swatch,
  &__icon {
    display: grid;
    gap: var(--space-2);
    justify-items: start;
  }

  &__swatch-color {
    width: 100%;
    height: var(--space-12);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__icons {
    grid-template-columns: repeat(auto-fill, minmax(var(--layout-icon-cell), 1fr));
  }

  &__icon {
    justify-items: center;
    padding: var(--space-3);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
  }

  &__icon-glyph {
    font-size: var(--font-size-xl);
  }

  &__code {
    font-size: var(--font-size-xs);
    color: var(--color-text-subtle);
    overflow-wrap: anywhere;
  }

  &__type {
    display: flex;
    gap: var(--space-4);
    align-items: baseline;
    line-height: var(--line-height-tight);
    letter-spacing: var(--letter-spacing-tight);
  }

  &__card-title {
    font-weight: var(--font-weight-semibold);
  }
}
</style>
