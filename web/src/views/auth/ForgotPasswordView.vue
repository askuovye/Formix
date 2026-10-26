<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { reactive, useTemplateRef } from 'vue'

import { forgotPassword } from '@/api/auth'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import AppTextLink from '@/components/AppTextLink.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import AuthCard from '@/features/auth/AuthCard.vue'
import AuthForm from '@/features/auth/AuthForm.vue'
import { forgotPasswordSchema } from '@/features/auth/schemas'
import { auth } from '@/strings'

const t = auth.forgot
const form = useTemplateRef('form')

const values = reactive({ email: '' })
const { errors, formError, validate, applyServerError, focusFirstError } = useFormValidation(
  forgotPasswordSchema,
  values,
)

// A API responde a mesma mensagem genérica exista ou não a conta.
const mutation = useMutation({
  mutationFn: (email: string) => forgotPassword(email),
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

  mutation.mutate(data.email)
}
</script>

<template>
  <AuthCard v-if="mutation.isSuccess.value" :title="t.doneTitle">
    <AppAlert tone="success">{{ mutation.data.value }}</AppAlert>
    <AppButton to="/entrar" variant="secondary" block>{{ t.back }}</AppButton>
  </AuthCard>

  <AuthCard v-else :title="t.title" :subtitle="t.subtitle">
    <AuthForm ref="form" :error="formError" @submit="submit">
      <AppTextField
        v-model="values.email"
        type="email"
        :label="auth.fields.email"
        autocomplete="email"
        :error="errors.email"
        required
      />
      <AppButton type="submit" block :loading="mutation.isPending.value">{{ t.submit }}</AppButton>
    </AuthForm>

    <template #footer>
      <AppTextLink to="/entrar">{{ t.back }}</AppTextLink>
    </template>
  </AuthCard>
</template>
