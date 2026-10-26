import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

import { PRESET_COLORS } from '@/features/renderer/theme'

import BuilderDesignPanel from '../BuilderDesignPanel.vue'
import { useBuilderStore } from '../useBuilderStore'

function setup() {
  const store = useBuilderStore()
  store.load('f1', { fields: [] })
  const wrapper = mount(BuilderDesignPanel, { attachTo: document.body })

  return { store, wrapper }
}

function preset(wrapper: ReturnType<typeof setup>['wrapper'], name: string) {
  const button = wrapper.findAll('.design-panel__preset').find((item) => item.text() === name)
  if (!button) throw new Error(`Preset ${name} não encontrado`)
  return button
}

describe('BuilderDesignPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sem tema, o Clássico aparece escolhido', () => {
    const { wrapper } = setup()

    expect(preset(wrapper, 'Clássico').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('escolher um preset aplica as cores dele', async () => {
    const { store, wrapper } = setup()

    await preset(wrapper, 'Escuro').trigger('click')

    expect(store.schema.theme).toMatchObject({ preset: 'dark', colors: PRESET_COLORS.dark })
    expect(preset(wrapper, 'Escuro').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('mudar uma cor vira Personalizado e mantém as outras; pouco contraste avisa', async () => {
    const { store, wrapper } = setup()

    await wrapper.findAll('.color-field__code')[3]?.setValue('#EEEEEE')

    expect(store.schema.theme?.preset).toBe('custom')
    expect(store.schema.theme?.colors).toEqual({ ...PRESET_COLORS.classic, text: '#EEEEEE' })
    expect(wrapper.text()).toContain('Pouco contraste entre o texto e o cartão')
    wrapper.unmount()
  })
})
