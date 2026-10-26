<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import AppIconButton from '@/components/AppIconButton.vue'
import FormThemeScope from '@/features/renderer/FormThemeScope.vue'
import { builder } from '@/strings'

import BuilderCanvas from './BuilderCanvas.vue'
import BuilderDragLift from './BuilderDragLift.vue'
import BuilderEdgePanel from './BuilderEdgePanel.vue'
import BuilderProperties from './BuilderProperties.vue'
import BuilderSidebar from './BuilderSidebar.vue'
import type { BuilderSection } from './sections'
import { useBuilderStore } from './useBuilderStore'

/** Gavetas do celular (abaixo de lg as colunas laterais somem). */
const menuOpen = defineModel<boolean>('menuOpen', { default: false })
const propertiesOpen = defineModel<boolean>('propertiesOpen', { default: false })
/** Seção aberta na coluna da esquerda (ou na gaveta do menu). */
const section = defineModel<BuilderSection>('section', { default: 'fields' })

/** Celular: da lista de condições direto para as propriedades do campo. */
function openPropertiesDrawer(): void {
  menuOpen.value = false
  propertiesOpen.value = true
}

function openFields(): void {
  section.value = 'fields'
  menuOpen.value = true
}

const store = useBuilderStore()

// Selecionar um campo no canvas abre as propriedades (desktop): é quase sempre
// o próximo passo. Adicionar pela paleta não abre (seriam dois painéis).
const propertiesPanel = ref<InstanceType<typeof BuilderEdgePanel> | null>(null)

watch(
  () => store.selections,
  () => propertiesPanel.value?.show(),
)

function isEditable(target: EventTarget | null): boolean {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
  )
}

// Ctrl/⌘+Z desfaz e Ctrl/⌘+Shift+Z ou Ctrl+Y refaz. Dentro de campos de texto
// fica o desfazer nativo do navegador.
function onKeydown(event: KeyboardEvent): void {
  if (!(event.ctrlKey || event.metaKey) || isEditable(event.target)) return

  const key = event.key.toLowerCase()

  if (key === 'z' && !event.shiftKey) {
    event.preventDefault()
    store.undo()
  } else if ((key === 'z' && event.shiftKey) || key === 'y') {
    event.preventDefault()
    store.redo()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="workspace">
    <!-- Desktop: painéis recolhidos que deslizam ao encostar o mouse na borda. -->
    <div class="workspace__edge">
      <BuilderEdgePanel side="start">
        <BuilderSidebar v-model:section="section" />
      </BuilderEdgePanel>
    </div>

    <!-- O canvas já mostra o tema do formulário (cores, fonte, cantos). -->
    <FormThemeScope
      id="conteudo"
      tag="main"
      class="workspace__canvas"
      tabindex="-1"
      :theme="store.schema.theme"
    >
      <BuilderCanvas />
    </FormThemeScope>
    <BuilderDragLift />

    <!-- No celular: desfazer/refazer e "Adicionar campo" flutuam ao alcance do polegar. -->
    <div class="workspace__history">
      <AppIconButton
        icon="undo-2"
        :label="builder.toolbar.undo"
        :disabled="!store.canUndo"
        @click="store.undo()"
      />
      <AppIconButton
        icon="redo-2"
        :label="builder.toolbar.redo"
        :disabled="!store.canRedo"
        @click="store.redo()"
      />
    </div>
    <AppButton icon-left="plus" class="workspace__fab" @click="openFields">
      {{ builder.toolbar.openPalette }}
    </AppButton>

    <div class="workspace__edge">
      <BuilderEdgePanel ref="propertiesPanel" side="end">
        <div class="workspace__properties">
          <BuilderProperties />
        </div>
      </BuilderEdgePanel>
    </div>

    <AppDrawer v-model:open="menuOpen" :title="builder.rail.label" width="panel">
      <div class="workspace__menu">
        <AppButton
          :to="{ name: 'forms' }"
          variant="ghost"
          size="sm"
          icon-left="arrow-left"
          class="workspace__back"
        >
          {{ builder.back }}
        </AppButton>
        <BuilderSidebar
          v-model:section="section"
          layout="drawer"
          @added="menuOpen = false"
          @picked="openPropertiesDrawer"
        />
      </div>
    </AppDrawer>
    <AppDrawer
      v-model:open="propertiesOpen"
      :title="builder.properties.title"
      side="end"
      width="panel"
    >
      <div class="workspace__drawer">
        <BuilderProperties />
      </div>
    </AppDrawer>

    <p class="workspace__announcer" aria-live="polite">{{ store.announcement }}</p>
  </div>
</template>

<style scoped lang="scss">
.workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-height: 0;

  // Abaixo de lg os painéis são gavetas (menu e propriedades).
  &__edge {
    display: none;

    @include up(lg) {
      display: contents;
    }
  }

  &__properties {
    padding: var(--space-4);
  }

  &__canvas {
    // O fim da lista fica acima do botão flutuante.
    padding: var(--space-6) var(--space-4) calc(var(--space-16) + var(--space-8));
    overflow-y: auto;
    outline: none;
  }

  &__fab {
    position: fixed;
    inset-block-end: calc(var(--space-4) + env(safe-area-inset-bottom));
    inset-inline-end: var(--space-4);
    z-index: var(--layer-raised);
    box-shadow: var(--shadow-lg);
  }

  &__history {
    position: fixed;
    inset-block-end: calc(var(--space-4) + env(safe-area-inset-bottom));
    inset-inline-start: var(--space-4);
    z-index: var(--layer-raised);
    display: flex;
    padding: var(--space-1);
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-full);
    box-shadow: var(--shadow-lg);

    @include up(md) {
      display: none;
    }
  }

  &__drawer {
    padding: var(--space-4);
  }

  &__menu {
    display: grid;
    gap: var(--space-2);
    padding-block-start: var(--space-2);
  }

  &__back {
    justify-self: start;
    margin-inline-start: var(--space-2);
  }

  &__announcer {
    @include visually-hidden;
  }

  @include up(lg) {
    position: relative;
    overflow: hidden;

    &__canvas {
      padding: var(--space-6) var(--space-8);
      // As bordas da tela abrem os painéis; a barra de rolagem ficaria embaixo
      // da área da direita. Rola pela roda, trackpad e teclado.
      scrollbar-width: none;
    }

    &__fab {
      display: none;
    }
  }
}
</style>
