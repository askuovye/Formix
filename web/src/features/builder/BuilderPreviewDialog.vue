<script setup lang="ts">
import { ref, watch } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import FormPage from '@/features/renderer/FormPage.vue'
import FormStatusPanel from '@/features/renderer/FormStatusPanel.vue'
import PublicFormRenderer from '@/features/renderer/PublicFormRenderer.vue'
import { builder } from '@/strings'

import { useBuilderStore } from './useBuilderStore'
import { useFormDetailsStore } from './useFormDetailsStore'

/**
 * Pré-visualização do rascunho atual (mesmo sem salvar ou publicar), com o
 * renderer de verdade e o tema. Valida como na página pública, mas não envia.
 */
const open = defineModel<boolean>('open', { default: false })

const store = useBuilderStore()
const detailsStore = useFormDetailsStore()
const t = builder.preview

const device = ref<'desktop' | 'mobile'>('desktop')
const done = ref(false)
/** Trocar a chave remonta o renderer: respostas, etapa e erros zerados. */
const run = ref(0)

function restart(): void {
  done.value = false
  run.value++
}

watch(open, (isOpen) => {
  if (isOpen) restart()
})
</script>

<template>
  <AppDialog v-model:open="open" :title="t.title" size="full">
    <div class="preview">
      <div class="preview__toolbar">
        <p class="preview__notice">{{ t.notice }}</p>
        <div class="preview__devices" role="group" :aria-label="t.devices">
          <AppButton
            size="sm"
            :variant="device === 'desktop' ? 'secondary' : 'ghost'"
            icon-left="monitor"
            :aria-pressed="device === 'desktop'"
            @click="device = 'desktop'"
          >
            {{ t.desktop }}
          </AppButton>
          <AppButton
            size="sm"
            :variant="device === 'mobile' ? 'secondary' : 'ghost'"
            icon-left="smartphone"
            :aria-pressed="device === 'mobile'"
            @click="device = 'mobile'"
          >
            {{ t.mobile }}
          </AppButton>
        </div>
        <AppButton size="sm" variant="ghost" icon-left="rotate-ccw" @click="restart">
          {{ t.restart }}
        </AppButton>
      </div>

      <div class="preview__stage">
        <div class="preview__frame" :class="`preview__frame--${device}`">
          <FormPage
            :theme="store.schema.theme"
            :title="done ? undefined : detailsStore.details?.title"
            :description="done ? undefined : detailsStore.details?.description"
            bare
          >
            <FormStatusPanel v-if="done" icon="circle-check" :title="t.doneTitle" tone="success">
              <p class="preview__done">{{ t.doneText }}</p>
              <AppButton variant="secondary" icon-left="rotate-ccw" @click="restart">
                {{ t.restart }}
              </AppButton>
            </FormStatusPanel>
            <PublicFormRenderer
              v-else
              :key="run"
              :items="store.items"
              :button-style="store.schema.theme?.button_style"
              preview
              @previewed="done = true"
            />
          </FormPage>
        </div>
      </div>
    </div>
  </AppDialog>
</template>

<style scoped lang="scss">
.preview {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: var(--space-3);
  block-size: 100%;

  &__toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2) var(--space-4);
    align-items: center;
  }

  &__notice {
    flex: 1;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__devices {
    display: flex;
    gap: var(--space-1);
  }

  &__stage {
    display: grid;
    justify-items: center;
    min-block-size: 0;
    overflow: hidden;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
  }

  &__frame {
    inline-size: 100%;
    block-size: 100%;
    overflow-y: auto;
    transition: inline-size var(--duration-slow) var(--ease-out);

    &--mobile {
      inline-size: min(100%, var(--preview-mobile-width));
      border-inline: var(--border-width) solid var(--color-border);
    }
  }

  &__done {
    color: var(--color-text-muted);
  }
}
</style>
