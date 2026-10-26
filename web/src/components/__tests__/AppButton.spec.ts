import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'

import AppButton from '../AppButton.vue'
import AppIconButton from '../AppIconButton.vue'

describe('AppButton', () => {
  it('é um button type="button" por padrão e emite click', async () => {
    const wrapper = mount(AppButton, { slots: { default: 'Salvar' } })
    const button = wrapper.get('button')

    expect(button.attributes('type')).toBe('button')
    expect(button.text()).toBe('Salvar')

    await button.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('carregando: desabilita, marca aria-busy e anuncia o carregamento', () => {
    const button = mount(AppButton, {
      props: { loading: true },
      slots: { default: 'Salvar' },
    }).get('button')

    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')
    expect(button.get('[role="img"]').attributes('aria-label')).toBe('Carregando…')
  })

  it('desabilitado não dispara click', async () => {
    const wrapper = mount(AppButton, { props: { disabled: true } })

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('com `to`, renderiza um link de rota', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', component: { render: () => null } }],
    })
    const wrapper = mount(AppButton, {
      props: { to: '/' },
      slots: { default: 'Início' },
      global: { plugins: [router] },
    })
    await router.isReady()

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.get('a').attributes('href')).toBe('/')
  })
})

describe('AppIconButton', () => {
  it('usa o label como nome acessível e esconde o ícone', () => {
    const button = mount(AppIconButton, { props: { icon: 'x', label: 'Fechar' } }).get('button')

    expect(button.attributes('aria-label')).toBe('Fechar')
    expect(button.get('svg').attributes('aria-hidden')).toBe('true')
  })
})
