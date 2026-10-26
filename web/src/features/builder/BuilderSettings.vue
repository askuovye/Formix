<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import AppNumberField from '@/components/AppNumberField.vue'
import AppSelect from '@/components/AppSelect.vue'
import AppSwitch from '@/components/AppSwitch.vue'
import AppTextarea from '@/components/AppTextarea.vue'
import AppTextField from '@/components/AppTextField.vue'
import { builder } from '@/strings'
import type { FormSettings } from '@/types/api'

import { DETAILS_LIMITS, emailFields, fromLocalInput, toLocalInput } from './details'
import { useBuilderStore } from './useBuilderStore'
import { useFormDetailsStore } from './useFormDetailsStore'

/** Configurações do formulário: notificações, limites, período e privacidade. */
const store = useFormDetailsStore()
const builderStore = useBuilderStore()
const t = builder.settings

const settings = computed(() => store.details?.settings)

function set<K extends keyof FormSettings>(key: K, value: FormSettings[K]): void {
  store.update((draft) => (draft.settings[key] = value), `settings.${key}`)
}

const errorFor = (key: keyof FormSettings) => store.errors[`settings.${key}`]

// Lista de e-mails editada como texto (um por linha); o texto local preserva
// as linhas em branco enquanto se digita.
const emailsText = ref('')

function parseEmails(text: string): string[] {
  return text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line !== '')
}

watch(
  () => settings.value?.notification_emails.join('\n'),
  (joined) => {
    if (parseEmails(emailsText.value).join('\n') !== joined) emailsText.value = joined ?? ''
  },
  { immediate: true },
)

function setEmails(text: string): void {
  emailsText.value = text
  set('notification_emails', parseEmails(text))
}

// Cópia para quem responde: precisa de um campo de e-mail do formulário.
const emailOptions = computed(() =>
  emailFields(builderStore.items).map((field) => ({ value: field.id, label: field.label })),
)

function setSendCopy(on: boolean): void {
  store.update((draft) => {
    draft.settings.send_copy_to_respondent = on
    // Ligou sem campo escolhido: sugere o primeiro de e-mail.
    if (on && !emailOptions.value.some((o) => o.value === draft.settings.respondent_email_field)) {
      draft.settings.respondent_email_field = emailOptions.value[0]?.value ?? null
    }
  }, 'settings.send_copy_to_respondent')
}
</script>

<template>
  <div v-if="settings" class="settings-panel">
    <h2 class="settings-panel__title">{{ t.title }}</h2>
    <p class="settings-panel__intro">{{ t.intro }}</p>

    <section class="settings-panel__section">
      <h3 class="settings-panel__heading">{{ t.sections.notifications }}</h3>
      <AppTextarea
        :model-value="emailsText"
        :label="t.notificationEmails"
        :hint="t.notificationEmailsHint(DETAILS_LIMITS.notificationEmails)"
        :rows="3"
        :error="errorFor('notification_emails')"
        @update:model-value="setEmails"
      />
    </section>

    <section class="settings-panel__section">
      <h3 class="settings-panel__heading">{{ t.sections.responses }}</h3>
      <AppNumberField
        :model-value="settings.max_submissions"
        :label="t.maxSubmissions"
        :hint="t.maxSubmissionsHint"
        :min="1"
        :max="DETAILS_LIMITS.maxSubmissions"
        :error="errorFor('max_submissions')"
        @update:model-value="(value) => set('max_submissions', value)"
      />
      <AppTextField
        type="datetime-local"
        :model-value="toLocalInput(settings.opens_at)"
        :label="t.opensAt"
        :error="errorFor('opens_at')"
        @update:model-value="(value) => set('opens_at', fromLocalInput(value))"
      />
      <AppTextField
        type="datetime-local"
        :model-value="toLocalInput(settings.closes_at)"
        :label="t.closesAt"
        :hint="t.datesHint"
        :error="errorFor('closes_at')"
        @update:model-value="(value) => set('closes_at', fromLocalInput(value))"
      />
      <AppTextarea
        :model-value="settings.success_message ?? ''"
        :label="t.successMessage"
        :placeholder="t.successMessagePlaceholder"
        :rows="3"
        :maxlength="DETAILS_LIMITS.successMessageMax"
        :error="errorFor('success_message')"
        @update:model-value="(value) => set('success_message', value === '' ? null : value)"
      />
    </section>

    <section class="settings-panel__section">
      <h3 class="settings-panel__heading">{{ t.sections.respondent }}</h3>
      <AppSwitch
        :model-value="settings.send_copy_to_respondent"
        :label="t.sendCopy"
        :hint="emailOptions.length > 0 ? t.sendCopyHint : t.sendCopyNeedsEmail"
        :disabled="emailOptions.length === 0 && !settings.send_copy_to_respondent"
        @update:model-value="setSendCopy"
      />
      <AppSelect
        v-if="settings.send_copy_to_respondent"
        :model-value="settings.respondent_email_field ?? undefined"
        :label="t.respondentEmailField"
        :options="emailOptions"
        :error="errorFor('respondent_email_field')"
        @update:model-value="(value) => set('respondent_email_field', value ?? null)"
      />
      <AppSwitch
        :model-value="settings.allow_respondent_edit"
        :label="t.allowEdit"
        :hint="t.allowEditHint"
        @update:model-value="(value) => set('allow_respondent_edit', value)"
      />
    </section>

    <section class="settings-panel__section">
      <h3 class="settings-panel__heading">{{ t.sections.privacy }}</h3>
      <AppNumberField
        :model-value="settings.retention_days"
        :label="t.retentionDays"
        :hint="t.retentionDaysHint"
        :min="1"
        :max="DETAILS_LIMITS.retentionDays"
        :error="errorFor('retention_days')"
        @update:model-value="(value) => set('retention_days', value)"
      />
    </section>
  </div>
</template>

<style scoped lang="scss">
.settings-panel {
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
}
</style>
