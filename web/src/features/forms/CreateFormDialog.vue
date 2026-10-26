<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { reactive, useTemplateRef, watch } from 'vue'
import { useRouter } from 'vue-router'

import { createForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppAlert from '@/components/AppAlert.vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppTextField from '@/components/AppTextField.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import { forms } from '@/strings'

import { createFormSchema } from './schemas'

const open = defineModel<boolean>('open', { default: false })

const t = forms.createDialog
const router = useRouter()
const queryClient = useQueryClient()
const formEl = useTemplateRef('form')

const values = reactive({ title: '' })
const { errors, formError, validate, applyServerError, focusFirstError, reset } = useFormValidation(
  createFormSchema,
  values,
)

// Cada abertura começa limpa.
watch(open, (isOpen) => {
  if (isOpen) {
    values.title = ''
    reset()
  }
})

const mutation = useMutation({
  mutationFn: createForm,
  onSuccess: async (form) => {
    queryClient.setQueryData(queryKeys.form(form.id), form)
    await queryClient.invalidateQueries({ queryKey: queryKeys.forms, refetchType: 'none' })
    open.value = false
    await router.push({ name: 'form-builder', params: { id: form.id } })
  },
  onError: async (error) => {
    applyServerError(error)
    await focusFirstError(formEl.value)
  },
})

async function submit(): Promise<void> {
  const data = validate()

  if (!data) {
    await focusFirstError(formEl.value)
    return
  }

  mutation.mutate(data)
}
</script>

<template>
  <AppDialog v-model:open="open" :title="t.title" :description="t.description">
    <form ref="form" class="create-form" novalidate @submit.prevent="submit">
      <AppAlert v-if="formError" tone="danger">{{ formError }}</AppAlert>
      <AppTextField
        v-model="values.title"
        :label="t.field"
        :placeholder="t.placeholder"
        :error="errors.title"
        autocomplete="off"
        required
      />
      <div class="create-form__actions">
        <AppButton variant="secondary" :disabled="mutation.isPending.value" @click="open = false">
          {{ t.cancel }}
        </AppButton>
        <AppButton type="submit" :loading="mutation.isPending.value">{{ t.submit }}</AppButton>
      </div>
    </form>
  </AppDialog>
</template>

<style scoped lang="scss">
.create-form {
  display: grid;
  gap: var(--space-5);

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    justify-content: flex-end;
  }
}
</style>
