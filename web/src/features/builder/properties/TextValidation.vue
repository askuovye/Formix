<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppNumberField from '@/components/AppNumberField.vue'
import AppSelect from '@/components/AppSelect.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import type { Rule, RuleType, Strictness } from '@/types/schema'

import {
  FORMAT_RULES,
  RULE_LIMITS,
  type FormatRule,
  type TextField,
  applyStrictness,
  findRule,
  formatOf,
  isTextField,
  parseBlocklist,
  setFormat,
  setRule,
} from '../rules'
import type { ItemErrors } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'

import RuleToggle from './RuleToggle.vue'

/** Formato, limites de tamanho, nível de rigor e regras anti-lixo de um campo de texto. */
const props = defineProps<{
  item: TextField
  errors: ItemErrors
}>()

const store = useBuilderStore()
const t = builder.properties.rules
const tf = builder.properties.format

function change(recipe: (item: TextField) => void, key: string): void {
  store.update(
    props.item.id,
    (target) => {
      if (target.kind === 'input' && isTextField(target)) recipe(target)
    },
    key,
  )
}

/** Liga, desliga ou altera uma regra mantendo a mensagem própria que já existir. */
function put(type: RuleType, rule: Rule | null): void {
  change((item) => setRule(item, type, rule), `rules.${type}`)
}

function message(type: RuleType, value: string): void {
  change((item) => {
    const rule = findRule(item, type)
    if (!rule) return
    if (value === '') delete rule.message
    else rule.message = value
  }, `rules.${type}.message`)
}

function withMessage<T extends Rule>(type: RuleType, rule: T): T {
  const current = findRule(props.item, type)

  return current?.message ? { ...rule, message: current.message } : rule
}

// Formato (só texto curto)
const formatOptions = [
  { value: 'none', label: tf.none },
  ...FORMAT_RULES.map((format) => ({ value: format, label: tf[format] })),
]
const format = computed(() => formatOf(props.item) ?? 'none')

function setFormatValue(value: string | undefined): void {
  const next = (FORMAT_RULES as readonly string[]).includes(value ?? '')
    ? (value as FormatRule)
    : null
  change((item) => setFormat(item, next), 'rules.format')
}

// Limites numéricos: campo vazio = sem a regra.
function setNumberRule(
  type: 'min_length' | 'max_length' | 'min_words',
  value: number | null,
): void {
  put(type, value === null ? null : withMessage(type, { type, value }))
}

// Nível de rigor
const levels: Strictness[] = ['leve', 'medio', 'estrito']

function applyLevel(level: Strictness): void {
  change((item) => applyStrictness(item, level), 'strictness')
}

// Regras anti-lixo
const repeated = computed(() => findRule(props.item, 'no_repeated_chars'))
const sequences = computed(() => findRule(props.item, 'no_sequences'))
const distinct = computed(() => findRule(props.item, 'min_distinct_chars'))
const blocklist = computed(() => findRule(props.item, 'blocklist'))

// A lista é editada como texto livre (uma por linha); o texto local preserva
// as linhas em branco enquanto se digita.
const blocklistText = ref('')

watch(
  () => [props.item.id, blocklist.value?.values.join('\n')] as const,
  ([, joined], previous) => {
    const changedItem = previous?.[0] !== props.item.id
    if (changedItem || parseBlocklist(blocklistText.value).join('\n') !== joined) {
      blocklistText.value = joined ?? ''
    }
  },
  { immediate: true },
)

function setBlocklist(text: string): void {
  blocklistText.value = text
  put('blocklist', withMessage('blocklist', { type: 'blocklist', values: parseBlocklist(text) }))
}

const DEFAULTS = {
  repeatedThreshold: 0.8,
  distinct: 3,
  blocklist: ['teste', 'asdf', 'xxx', 'n/a'],
} as const

const errorFor = (type: RuleType) => props.errors[`rules.${type}`]
const messageErrorFor = (type: RuleType) => props.errors[`rules.${type}.message`]
</script>

<template>
  <div class="text-validation">
    <template v-if="props.item.type === 'short_text'">
      <AppSelect
        :model-value="format"
        :label="tf.label"
        :hint="tf.hint"
        :options="formatOptions"
        @update:model-value="setFormatValue"
      />
      <AppTextField
        v-if="format !== 'none'"
        :model-value="findRule(props.item, format as FormatRule)?.message ?? ''"
        :label="t.message"
        :placeholder="t.messagePlaceholder"
        :maxlength="RULE_LIMITS.message"
        :error="messageErrorFor(format as FormatRule)"
        @update:model-value="(value) => message(format as FormatRule, value)"
      />
    </template>

    <div class="text-validation__pair">
      <AppNumberField
        :model-value="findRule(props.item, 'min_length')?.value ?? null"
        :label="t.minLength"
        :min="RULE_LIMITS.length.min"
        :max="RULE_LIMITS.length.max"
        :error="errorFor('min_length')"
        @update:model-value="(value) => setNumberRule('min_length', value)"
      />
      <AppNumberField
        :model-value="findRule(props.item, 'max_length')?.value ?? null"
        :label="t.maxLength"
        :min="RULE_LIMITS.length.min"
        :max="RULE_LIMITS.length.max"
        :error="errorFor('max_length')"
        @update:model-value="(value) => setNumberRule('max_length', value)"
      />
    </div>
    <AppNumberField
      v-if="props.item.type === 'long_text'"
      :model-value="findRule(props.item, 'min_words')?.value ?? null"
      :label="t.minWords"
      :min="RULE_LIMITS.minWords.min"
      :max="RULE_LIMITS.minWords.max"
      :error="errorFor('min_words')"
      @update:model-value="(value) => setNumberRule('min_words', value)"
    />
    <p class="text-validation__hint">{{ t.emptyMeansOff }}</p>

    <section class="text-validation__section" :aria-label="builder.properties.sections.antiJunk">
      <h3 class="text-validation__heading">{{ builder.properties.sections.antiJunk }}</h3>

      <div class="text-validation__levels" role="group" :aria-label="t.strictness">
        <p class="text-validation__levels-label">{{ t.strictness }}</p>
        <div class="text-validation__level-buttons">
          <AppButton
            v-for="level in levels"
            :key="level"
            size="sm"
            :variant="props.item.strictness === level ? 'primary' : 'secondary'"
            :aria-pressed="props.item.strictness === level"
            @click="applyLevel(level)"
          >
            {{ t.strictnessLevels[level] }}
          </AppButton>
        </div>
        <p class="text-validation__hint">
          {{
            props.item.strictness
              ? t.strictnessPending(t.strictnessLevels[props.item.strictness])
              : t.strictnessHint
          }}
        </p>
      </div>

      <RuleToggle
        :label="t.noOnlySymbols"
        :hint="t.noOnlySymbolsHint"
        :enabled="!!findRule(props.item, 'no_only_symbols')"
        :message="findRule(props.item, 'no_only_symbols')?.message"
        :message-error="messageErrorFor('no_only_symbols')"
        @toggle="(on) => put('no_only_symbols', on ? { type: 'no_only_symbols' } : null)"
        @message="(value) => message('no_only_symbols', value)"
      />

      <RuleToggle
        :label="t.noRepeatedChars"
        :hint="t.noRepeatedCharsHint"
        :enabled="!!repeated"
        :message="repeated?.message"
        :message-error="messageErrorFor('no_repeated_chars')"
        @toggle="
          (on) =>
            put(
              'no_repeated_chars',
              on ? { type: 'no_repeated_chars', threshold: DEFAULTS.repeatedThreshold } : null,
            )
        "
        @message="(value) => message('no_repeated_chars', value)"
      >
        <AppNumberField
          :model-value="repeated ? Math.round(repeated.threshold * 100) : null"
          :label="t.repeatedPercent"
          :min="RULE_LIMITS.repeatedPercent.min"
          :max="RULE_LIMITS.repeatedPercent.max"
          :error="errorFor('no_repeated_chars')"
          @update:model-value="
            (value) =>
              put(
                'no_repeated_chars',
                withMessage('no_repeated_chars', {
                  type: 'no_repeated_chars',
                  threshold: value === null ? Number.NaN : value / 100,
                }),
              )
          "
        />
      </RuleToggle>

      <RuleToggle
        :label="t.noSequences"
        :hint="t.noSequencesHint"
        :enabled="!!sequences"
        :message="sequences?.message"
        :message-error="messageErrorFor('no_sequences')"
        @toggle="(on) => put('no_sequences', on ? { type: 'no_sequences' } : null)"
        @message="(value) => message('no_sequences', value)"
      >
        <AppNumberField
          :model-value="sequences?.value ?? null"
          :label="t.sequenceLength"
          :min="RULE_LIMITS.sequence.min"
          :max="RULE_LIMITS.sequence.max"
          :error="errorFor('no_sequences')"
          @update:model-value="
            (value) =>
              put(
                'no_sequences',
                withMessage(
                  'no_sequences',
                  value === null ? { type: 'no_sequences' } : { type: 'no_sequences', value },
                ),
              )
          "
        />
      </RuleToggle>

      <RuleToggle
        :label="t.minDistinctChars"
        :hint="t.minDistinctCharsHint"
        :enabled="!!distinct"
        :message="distinct?.message"
        :message-error="messageErrorFor('min_distinct_chars')"
        @toggle="
          (on) =>
            put(
              'min_distinct_chars',
              on ? { type: 'min_distinct_chars', value: DEFAULTS.distinct } : null,
            )
        "
        @message="(value) => message('min_distinct_chars', value)"
      >
        <AppNumberField
          :model-value="distinct?.value ?? null"
          :label="t.distinctCount"
          :min="RULE_LIMITS.minDistinctChars.min"
          :max="RULE_LIMITS.minDistinctChars.max"
          :error="errorFor('min_distinct_chars')"
          @update:model-value="
            (value) =>
              put(
                'min_distinct_chars',
                withMessage('min_distinct_chars', {
                  type: 'min_distinct_chars',
                  value: value ?? Number.NaN,
                }),
              )
          "
        />
      </RuleToggle>

      <RuleToggle
        :label="t.blocklist"
        :hint="t.blocklistHint"
        :enabled="!!blocklist"
        :message="blocklist?.message"
        :message-error="messageErrorFor('blocklist')"
        @toggle="
          (on) =>
            put('blocklist', on ? { type: 'blocklist', values: [...DEFAULTS.blocklist] } : null)
        "
        @message="(value) => message('blocklist', value)"
      >
        <AppTextarea
          :model-value="blocklistText"
          :label="t.blocklistValues"
          :rows="5"
          :error="errorFor('blocklist')"
          @update:model-value="setBlocklist"
        />
      </RuleToggle>
    </section>
  </div>
</template>

<style scoped lang="scss">
.text-validation {
  display: grid;
  gap: var(--space-4);

  &__pair {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-3);
  }

  &__hint {
    margin-block-start: calc(-1 * var(--space-2));
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__section {
    display: grid;
    gap: var(--space-2);
  }

  &__heading {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
  }

  &__levels {
    display: grid;
    gap: var(--space-2);
    padding-block-end: var(--space-3);
    border-block-end: var(--border-width) solid var(--color-border);
  }

  &__levels-label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
  }

  &__level-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
  }

  &__levels &__hint {
    margin-block-start: 0;
  }
}
</style>
