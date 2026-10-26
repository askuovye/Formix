<script setup lang="ts">
import { computed } from 'vue'

import AppColorField from '@/components/AppColorField.vue'
import AppSelect from '@/components/AppSelect.vue'
import { PRESET_COLORS, contrastRatio, resolveTheme } from '@/features/renderer/theme'
import { builder } from '@/strings'
import type { FormTheme, ThemePreset } from '@/types/schema'

import ThemeImageField from './design/ThemeImageField.vue'
import { useBuilderStore } from './useBuilderStore'

/** Seção Design: tema (cores), formato, capa e logo. Tudo entra no desfazer. */
const store = useBuilderStore()
const t = builder.design

const theme = computed(() => resolveTheme(store.schema.theme))

const PRESETS: ThemePreset[] = ['classic', 'minimal', 'dark', 'modern', 'custom']
const COLOR_KEYS: (keyof FormTheme['colors'])[] = ['primary', 'background', 'surface', 'text']

/** Cores mostradas no cartão do preset (o Personalizado mostra as atuais). */
function swatches(preset: ThemePreset): string[] {
  const colors = preset === 'custom' ? theme.value.colors : PRESET_COLORS[preset]
  return [colors.background, colors.surface, colors.primary, colors.text]
}

function choosePreset(preset: ThemePreset): void {
  store.updateTheme((draft) => {
    draft.preset = preset
    if (preset !== 'custom') draft.colors = { ...PRESET_COLORS[preset] }
  }, 'preset')
}

/** Mexeu numa cor: o tema vira Personalizado (as outras cores ficam). */
function setColor(key: keyof FormTheme['colors'], value: string): void {
  store.updateTheme((draft) => {
    draft.preset = 'custom'
    draft.colors[key] = value
  }, `color:${key}`)
}

function set<K extends 'radius' | 'font' | 'button_style' | 'alignment'>(
  key: K,
  value: string | undefined,
  allowed: Record<FormTheme[K], string>,
): void {
  if (value === undefined || !(value in allowed)) return
  store.updateTheme((draft) => {
    draft[key] = value as FormTheme[K]
  }, key)
}

function setCover<K extends 'fit' | 'overlay_opacity'>(
  key: K,
  value: NonNullable<FormTheme['cover']>[K],
): void {
  store.updateTheme((draft) => {
    if (draft.cover) draft.cover[key] = value
  }, `cover:${key}`)
}

function setFit(value: string | undefined): void {
  if (value === 'cover' || value === 'contain') setCover('fit', value)
}

function options<T extends string>(labels: Record<T, string>): { value: T; label: string }[] {
  return (Object.entries(labels) as [T, string][]).map(([value, label]) => ({ value, label }))
}

/** Contraste mínimo (WCAG AA): texto normal 4,5:1; elementos de interface 3:1. */
const warnings = computed<string[]>(() => {
  const { text, surface, primary } = theme.value.colors
  const list: string[] = []
  if (contrastRatio(text, surface) < 4.5) list.push(t.lowTextContrast)
  if (contrastRatio(primary, surface) < 3) list.push(t.lowPrimaryContrast)
  return list
})
</script>

<template>
  <div class="design-panel">
    <h2 class="design-panel__title">{{ t.title }}</h2>
    <p class="design-panel__intro">{{ t.intro }}</p>

    <section class="design-panel__section">
      <h3 class="design-panel__heading">{{ t.presets }}</h3>
      <div class="design-panel__presets" role="group" :aria-label="t.presets">
        <button
          v-for="preset in PRESETS"
          :key="preset"
          type="button"
          class="design-panel__preset"
          :class="{ 'design-panel__preset--active': theme.preset === preset }"
          :aria-pressed="theme.preset === preset"
          @click="choosePreset(preset)"
        >
          <span class="design-panel__swatches" aria-hidden="true">
            <span
              v-for="(color, index) in swatches(preset)"
              :key="index"
              class="design-panel__swatch"
              :style="{ background: color }"
            />
          </span>
          {{ t.presetNames[preset] }}
        </button>
      </div>
    </section>

    <section class="design-panel__section">
      <h3 class="design-panel__heading">{{ t.colors }}</h3>
      <AppColorField
        v-for="key in COLOR_KEYS"
        :key="key"
        :model-value="theme.colors[key]"
        :label="t.colorNames[key]"
        @update:model-value="(value) => setColor(key, value)"
      />
      <p v-for="warning in warnings" :key="warning" class="design-panel__warning" role="status">
        {{ warning }}
      </p>
    </section>

    <section class="design-panel__section">
      <h3 class="design-panel__heading">{{ t.format }}</h3>
      <AppSelect
        :model-value="theme.font"
        :label="t.font"
        :options="options(t.fonts)"
        @update:model-value="(value) => set('font', value, t.fonts)"
      />
      <AppSelect
        :model-value="theme.radius"
        :label="t.radius"
        :options="options(t.radii)"
        @update:model-value="(value) => set('radius', value, t.radii)"
      />
      <AppSelect
        :model-value="theme.button_style"
        :label="t.button"
        :options="options(t.buttons)"
        @update:model-value="(value) => set('button_style', value, t.buttons)"
      />
      <AppSelect
        :model-value="theme.alignment"
        :label="t.alignment"
        :options="options(t.alignments)"
        @update:model-value="(value) => set('alignment', value, t.alignments)"
      />
    </section>

    <section class="design-panel__section">
      <h3 class="design-panel__heading">{{ t.images }}</h3>
      <ThemeImageField kind="cover" />
      <template v-if="theme.cover">
        <AppSelect
          :model-value="theme.cover.fit"
          :label="t.fit"
          :options="options(t.fits)"
          @update:model-value="setFit"
        />
        <label class="design-panel__range">
          <span class="design-panel__range-label">
            {{ t.overlay }}
            <output>{{ t.overlayValue(theme.cover.overlay_opacity) }}</output>
          </span>
          <input
            type="range"
            min="0"
            max="80"
            step="5"
            class="design-panel__range-input"
            :value="theme.cover.overlay_opacity"
            @input="setCover('overlay_opacity', Number(($event.target as HTMLInputElement).value))"
          />
        </label>
      </template>
      <ThemeImageField kind="logo" />
    </section>
  </div>
</template>

<style scoped lang="scss">
.design-panel {
  display: grid;
  gap: var(--space-4);
  align-content: start;

  &__title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
  }

  &__intro {
    margin-block-start: calc(-1 * var(--space-2));
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__section {
    display: grid;
    gap: var(--space-4);
    padding-block-start: var(--space-4);
    border-block-start: var(--border-width) solid var(--color-border);
  }

  &__heading {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__presets {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-2);
  }

  &__preset {
    display: grid;
    gap: var(--space-2);
    padding: var(--space-2);
    font: inherit;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    color: var(--color-text);
    text-align: start;
    cursor: pointer;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-md);
    transition: border-color var(--duration-fast) var(--ease-out);

    &:hover {
      border-color: var(--color-border-strong);
    }

    &:focus-visible {
      @include focus-ring;
    }

    &--active,
    &--active:hover {
      border-color: var(--color-primary);
      box-shadow: inset 0 0 0 var(--border-width) var(--color-primary);
    }
  }

  &__swatches {
    display: flex;
    overflow: hidden;
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-sm);
  }

  &__swatch {
    flex: 1;
    block-size: var(--space-5);
  }

  &__warning {
    font-size: var(--font-size-xs);
    color: var(--color-warning);
  }

  &__range {
    display: grid;
    gap: var(--space-2);
  }

  &__range-label {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__range-input {
    inline-size: 100%;
    accent-color: var(--color-primary);
  }
}
</style>
