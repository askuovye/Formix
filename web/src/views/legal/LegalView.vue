<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

import { fetchLegalDocument } from '@/api/legal'
import { queryKeys } from '@/api/queryKeys'
import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { legal } from '@/strings'
import type { LegalDocumentKind } from '@/types/api'

const props = defineProps<{
  document: LegalDocumentKind
}>()

const query = useQuery({
  queryKey: computed(() => queryKeys.legal(props.document)),
  queryFn: () => fetchLegalDocument(props.document),
  // O texto só muda com nova versão no servidor.
  staleTime: Infinity,
})

// "2026-10-23" é uma data sem fuso: formatar em UTC evita cair no dia anterior.
const dateFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long', timeZone: 'UTC' })

const meta = computed(() => {
  const doc = query.data.value

  return doc
    ? legal.meta(doc.version, dateFormatter.format(new Date(`${doc.updated_at}T00:00:00Z`)))
    : ''
})
</script>

<template>
  <article class="legal">
    <p v-if="query.isPending.value" class="legal__state" role="status">
      <AppSpinner />
      {{ legal.loading }}
    </p>

    <div v-else-if="query.isError.value" class="legal__state" role="alert">
      <AppIcon name="circle-alert" class="legal__error-icon" />
      <p>{{ legal.loadError }}</p>
      <AppButton variant="secondary" size="sm" icon-left="rotate-ccw" @click="query.refetch()">
        {{ legal.retry }}
      </AppButton>
    </div>

    <template v-else-if="query.data.value">
      <p class="legal__meta">{{ meta }}</p>
      <!-- HTML gerado e escapado pela própria API (D34); nunca usar v-html com dado de usuário. -->
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div class="legal__content prose" v-html="query.data.value.content_html" />
    </template>
  </article>
</template>

<style scoped lang="scss">
.legal {
  max-width: var(--layout-width-md);
  margin-inline: auto;

  &__meta {
    margin-block-end: var(--space-6);
    font-size: var(--font-size-sm);
    color: var(--color-text-subtle);
  }

  &__state {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: center;
    justify-content: center;
    padding-block: var(--space-16);
    color: var(--color-text-muted);
  }

  &__error-icon {
    color: var(--color-danger);
  }
}
</style>
