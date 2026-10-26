<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { reactive, useTemplateRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { login } from '@/api/auth'
import AppButton from '@/components/AppButton.vue'
import AppCheckbox from '@/components/AppCheckbox.vue'
import AppTextField from '@/components/AppTextField.vue'
import AppTextLink from '@/components/AppTextLink.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import AuthCard from '@/features/auth/AuthCard.vue'
import AuthForm from '@/features/auth/AuthForm.vue'
import { safeRedirect } from '@/features/auth/redirect'
import { loginSchema } from '@/features/auth/schemas'
import { useSessionStore } from '@/stores/useSessionStore'
import { auth } from '@/strings'

const t = auth.login
const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const form = useTemplateRef('form')

const values = reactive({ email: '', password: '', remember: false })
const { errors, formError, validate, applyServerError, focusFirstError } = useFormValidation(
  loginSchema,
  values,
)

const mutation = useMutation({
  mutationFn: login,
  onSuccess: async (user) => {
    session.setUser(user)
    await router.replace(safeRedirect(route.query.redirect))
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
  <AuthCard :title="t.title" :subtitle="t.subtitle">
    <AuthForm ref="form" :error="formError" @submit="submit">
      <AppTextField
        v-model="values.email"
        type="email"
        :label="auth.fields.email"
        autocomplete="email"
        :error="errors.email"
        required
      />
      <AppTextField
        v-model="values.password"
        type="password"
        :label="auth.fields.password"
        autocomplete="current-password"
        :error="errors.password"
        required
      />
      <div class="login__row">
        <AppCheckbox v-model="values.remember" :label="auth.fields.remember" />
        <AppTextLink to="/esqueci-a-senha">{{ t.forgot }}</AppTextLink>
      </div>
      <AppButton type="submit" block :loading="mutation.isPending.value">{{ t.submit }}</AppButton>
    </AuthForm>

    <template #footer>
      {{ t.noAccount }}
      <AppTextLink :to="{ path: '/criar-conta', query: route.query }">{{ t.register }}</AppTextLink>
    </template>
  </AuthCard>
</template>

<style scoped lang="scss">
.login__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: center;
  justify-content: space-between;
  font-size: var(--font-size-sm);
}
</style>
