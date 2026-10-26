<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { reactive, ref, useTemplateRef } from 'vue'
import { useRoute } from 'vue-router'

import { register } from '@/api/auth'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppCheckbox from '@/components/AppCheckbox.vue'
import AppTextField from '@/components/AppTextField.vue'
import AppTextLink from '@/components/AppTextLink.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import AuthCard from '@/features/auth/AuthCard.vue'
import AuthForm from '@/features/auth/AuthForm.vue'
import NewPasswordField from '@/features/auth/NewPasswordField.vue'
import { registerSchema } from '@/features/auth/schemas'
import { auth } from '@/strings'

const t = auth.register
const route = useRoute()
const form = useTemplateRef('form')

const values = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  terms_accepted: false,
})
const { errors, formError, validate, applyServerError, focusFirstError } = useFormValidation(
  registerSchema,
  values,
)

/** Email para o qual o link foi (ou seria) enviado: mostra a tela de conclusão. */
const sentTo = ref<string>()

const mutation = useMutation({
  mutationFn: register,
  onSuccess: (_message, payload) => {
    sentTo.value = payload.email
  },
  onError: async (error) => {
    applyServerError(error)
    await focusFirstError(form.value?.el)
  },
})

async function submit(): Promise<void> {
  const data = validate()

  if (!data) {
    await focusFirstError(form.value?.el)
    return
  }

  mutation.mutate(data)
}
</script>

<template>
  <AuthCard v-if="sentTo" :title="t.doneTitle">
    <AppAlert tone="success">{{ t.doneText(sentTo) }}</AppAlert>
    <AppButton :to="{ path: '/entrar', query: route.query }" variant="secondary" block>
      {{ t.doneAction }}
    </AppButton>
  </AuthCard>

  <AuthCard v-else :title="t.title" :subtitle="t.subtitle">
    <AuthForm ref="form" :error="formError" @submit="submit">
      <AppTextField
        v-model="values.name"
        :label="auth.fields.name"
        autocomplete="name"
        :error="errors.name"
        required
      />
      <AppTextField
        v-model="values.email"
        type="email"
        :label="auth.fields.email"
        autocomplete="email"
        :error="errors.email"
        required
      />
      <NewPasswordField
        v-model="values.password"
        :label="auth.fields.password"
        :error="errors.password"
      />
      <AppTextField
        v-model="values.password_confirmation"
        type="password"
        :label="auth.fields.passwordConfirmation"
        autocomplete="new-password"
        :error="errors.password_confirmation"
        required
      />
      <!-- Desmarcado por padrão (prova de consentimento, D20). Links abrem em nova aba para não perder o preenchimento. -->
      <AppCheckbox
        v-model="values.terms_accepted"
        :label="`${t.termsBefore}${t.terms}${t.termsMiddle}${t.privacy}`"
        :error="errors.terms_accepted"
      >
        <template #label>
          {{ t.termsBefore }}<AppTextLink to="/termos" external>{{ t.terms }}</AppTextLink
          >{{ t.termsMiddle }}<AppTextLink to="/privacidade" external>{{ t.privacy }}</AppTextLink>
        </template>
      </AppCheckbox>
      <AppButton type="submit" block :loading="mutation.isPending.value">{{ t.submit }}</AppButton>
    </AuthForm>

    <template #footer>
      {{ t.hasAccount }}
      <AppTextLink :to="{ path: '/entrar', query: route.query }">{{ t.login }}</AppTextLink>
    </template>
  </AuthCard>
</template>
