<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { computed, reactive, useTemplateRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { resetPassword } from '@/api/auth'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import AuthCard from '@/features/auth/AuthCard.vue'
import AuthForm from '@/features/auth/AuthForm.vue'
import { resetPasswordSchema } from '@/features/auth/schemas'
import { useToastStore } from '@/stores/useToastStore'
import { auth } from '@/strings'

const t = auth.reset
const route = useRoute()
const router = useRouter()
const toasts = useToastStore()
const form = useTemplateRef('form')

// Vêm do link do email: /redefinir-senha?token=…&email=…
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))
const hasLink = computed(() => token.value !== '' && email.value !== '')

const values = reactive({ password: '', password_confirmation: '' })
const { errors, formError, validate, applyServerError, focusFirstError } = useFormValidation(
  resetPasswordSchema,
  values,
)

// Token inválido ou expirado volta em errors.token: não há campo para ele, vira erro geral.
const generalError = computed(() => formError.value ?? errors.value.token ?? errors.value.email)

const mutation = useMutation({
  mutationFn: resetPassword,
  onSuccess: async (message) => {
    toasts.show({ title: t.success, description: message, tone: 'success' })
    await router.replace('/entrar')
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

  mutation.mutate({ ...data, token: token.value, email: email.value })
}
</script>

<template>
  <AuthCard v-if="!hasLink" :title="t.invalidTitle">
    <AppAlert tone="danger">{{ t.invalidText }}</AppAlert>
    <AppButton to="/esqueci-a-senha" variant="secondary" block>{{ t.requestNew }}</AppButton>
  </AuthCard>

  <AuthCard v-else :title="t.title" :subtitle="t.subtitle">
    <AuthForm ref="form" :error="generalError" @submit="submit">
      <!-- Ajuda gerenciadores de senha a associar a nova senha à conta certa. -->
      <input type="email" :value="email" autocomplete="username" hidden readonly />
      <AppTextField
        v-model="values.password"
        type="password"
        :label="auth.fields.newPassword"
        :hint="auth.fields.passwordHint"
        autocomplete="new-password"
        :error="errors.password"
        required
      />
      <AppTextField
        v-model="values.password_confirmation"
        type="password"
        :label="auth.fields.passwordConfirmation"
        autocomplete="new-password"
        :error="errors.password_confirmation"
        required
      />
      <AppButton type="submit" block :loading="mutation.isPending.value">{{ t.submit }}</AppButton>
    </AuthForm>
    <AppButton v-if="errors.token" to="/esqueci-a-senha" variant="secondary" block>
      {{ t.requestNew }}
    </AppButton>
  </AuthCard>
</template>
