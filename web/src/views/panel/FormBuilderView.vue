<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

import { fetchForm } from '@/api/forms'
import { queryKeys } from '@/api/queryKeys'
import AppAlert from '@/components/AppAlert.vue'
import AppBadge from '@/components/AppBadge.vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppSpinner from '@/components/AppSpinner.vue'
import { common, forms } from '@/strings'

// Provisório: o editor de verdade (paleta, prévia e propriedades) chega na fase 3b.
const props = defineProps<{
  id: string
}>()

const query = useQuery({
  queryKey: computed(() => queryKeys.form(props.id)),
  queryFn: () => fetchForm(props.id),
})
</script>

<template>
  <AppButton :to="{ name: 'forms' }" variant="ghost" size="sm" icon-left="arrow-left">
    {{ forms.builder.back }}
  </AppButton>

  <p v-if="query.isPending.value" class="builder-soon__status" role="status">
    <AppSpinner />
    {{ common.loading }}
  </p>

  <AppAlert v-else-if="query.isError.value" tone="danger">{{ forms.builder.loadError }}</AppAlert>

  <template v-else-if="query.data.value">
    <AppPageHeader :title="query.data.value.title" class="builder-soon__header">
      <template #actions>
        <AppBadge>{{ forms.status[query.data.value.status] }}</AppBadge>
      </template>
    </AppPageHeader>
    <AppCard padding="lg">
      <p class="builder-soon__text">{{ forms.builder.soon }}</p>
    </AppCard>
  </template>
</template>

<style scoped lang="scss">
.builder-soon {
  &__status {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    padding-block: var(--space-12);
    color: var(--color-text-muted);
  }

  &__header {
    margin-block-start: var(--space-4);
  }

  &__text {
    color: var(--color-text-muted);
  }
}
</style>
