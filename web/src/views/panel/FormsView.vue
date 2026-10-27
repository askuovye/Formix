<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppButton from '@/components/AppButton.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTabs, { type TabItem } from '@/components/AppTabs.vue'
import AppTextField from '@/components/AppTextField.vue'
import { useUrlSearch } from '@/composables/useUrlSearch'
import CreateFormDialog from '@/features/forms/CreateFormDialog.vue'
import FormsList from '@/features/forms/FormsList.vue'
import { forms } from '@/strings'
import type { FormStatus } from '@/types/api'

type Filter = FormStatus | 'all'

const route = useRoute()
const router = useRouter()

const tabs: TabItem[] = [
  { value: 'all', label: forms.tabs.all },
  { value: 'published', label: forms.tabs.published },
  { value: 'draft', label: forms.tabs.draft },
  { value: 'closed', label: forms.tabs.closed },
]

function isFilter(value: unknown): value is Filter {
  return tabs.some((tab) => tab.value === value)
}

// A aba fica na URL (?status=draft): sobrevive ao recarregar e ao voltar do editor.
const filter = computed<string>({
  get: () => (isFilter(route.query.status) ? route.query.status : 'all'),
  set: (value) => {
    void router.replace({ query: { ...route.query, status: value === 'all' ? undefined : value } })
  },
})

const { input: searchInput, term: search } = useUrlSearch()

const creating = ref(false)
</script>

<template>
  <AppPageHeader :title="forms.title" :subtitle="forms.subtitle">
    <template #actions>
      <AppButton icon-left="plus" @click="creating = true">{{ forms.create }}</AppButton>
    </template>
  </AppPageHeader>

  <AppTextField
    v-model="searchInput"
    type="search"
    :label="forms.search"
    :placeholder="forms.searchPlaceholder"
    icon-left="search"
    :maxlength="100"
    class="forms-page__search"
  />

  <AppTabs v-model="filter" :items="tabs" :label="forms.tabsLabel" variant="segmented">
    <template v-for="tab in tabs" :key="tab.value" #[tab.value]>
      <FormsList :status="tab.value as Filter" :search="search">
        <template v-if="tab.value === 'all'" #empty-action>
          <AppButton icon-left="plus" @click="creating = true">{{ forms.create }}</AppButton>
        </template>
      </FormsList>
    </template>
  </AppTabs>

  <CreateFormDialog v-model:open="creating" />
</template>

<style scoped lang="scss">
.forms-page__search {
  margin-block-end: var(--space-4);

  @include up(sm) {
    max-inline-size: var(--search-field-width);
  }
}
</style>
