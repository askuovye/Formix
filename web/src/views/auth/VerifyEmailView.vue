<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query'
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { resendEmailVerification, verifyEmail, type VerifyEmailParams } from '@/api/auth'
import { toApiError } from '@/api/client'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import AuthCard from '@/features/auth/AuthCard.vue'
import { useSessionStore } from '@/stores/useSessionStore'
import { useToastStore } from '@/stores/useToastStore'
import { auth } from '@/strings'

const t = auth.verify
const route = useRoute()
const session = useSessionStore()
const toasts = useToastStore()

// Vêm do link do email: /verificar-email?id=…&hash=…&expires=…&signature=…
const params = computed<VerifyEmailParams | null>(() => {
  const { id, hash, expires, signature } = route.query

  return typeof id === 'string' &&
    typeof hash === 'string' &&
    typeof expires === 'string' &&
    typeof signature === 'string'
    ? { id, hash, expires, signature }
    : null
})

const verification = useMutation({
  mutationFn: verifyEmail,
  // Logado: atualiza o usuário para sumir a faixa "confirme seu email".
  onSuccess: () => (session.isAuthenticated ? session.refresh() : undefined),
})

const resend = useMutation({
  mutationFn: resendEmailVerification,
  onSuccess: (message) => toasts.show({ title: t.resent, description: message, tone: 'success' }),
  onError: (error) => toasts.show({ title: toApiError(error).message, tone: 'danger' }),
})

const errorMessage = computed(() =>
  verification.error.value ? toApiError(verification.error.value).message : t.invalidText,
)

onMounted(async () => {
  await session.load().catch(() => undefined)

  if (params.value) {
    verification.mutate(params.value)
  }
})
</script>

<template>
  <AuthCard
    v-if="params && (verification.isIdle.value || verification.isPending.value)"
    :title="t.title"
  >
    <p class="verify__loading" role="status">
      <AppSpinner />
      {{ t.loading }}
    </p>
  </AuthCard>

  <AuthCard v-else-if="verification.isSuccess.value" :title="t.successTitle">
    <AppAlert tone="success">{{ verification.data.value }}</AppAlert>
    <AppButton :to="session.isAuthenticated ? '/painel' : '/entrar'" block>
      {{ session.isAuthenticated ? t.continuePanel : t.continueLogin }}
    </AppButton>
  </AuthCard>

  <AuthCard v-else :title="t.errorTitle">
    <AppAlert tone="danger">{{ errorMessage }}</AppAlert>
    <AppButton
      v-if="session.isAuthenticated"
      variant="secondary"
      block
      :loading="resend.isPending.value"
      @click="resend.mutate()"
    >
      {{ t.resend }}
    </AppButton>
    <AppButton v-else to="/entrar" variant="secondary" block>{{ t.continueLogin }}</AppButton>
  </AuthCard>
</template>

<style scoped lang="scss">
.verify__loading {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  justify-content: center;
  padding-block: var(--space-6);
  color: var(--color-text-muted);
}
</style>
