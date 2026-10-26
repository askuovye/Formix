import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import { delay } from '@/motion'
import { useToastStore } from '@/stores/useToastStore'

import AppToaster from '../AppToaster.vue'

describe('AppToaster', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('mostra o aviso dentro de uma região aria-live', async () => {
    const wrapper = mount(AppToaster)
    useToastStore().show({ title: 'Salvo', description: 'Tudo certo.', tone: 'success' })
    await nextTick()

    expect(wrapper.get('section').attributes('aria-live')).toBe('polite')
    expect(wrapper.get('li').text()).toContain('Salvo')
    expect(wrapper.get('li').text()).toContain('Tudo certo.')
  })

  it('aviso de erro usa role="alert"', async () => {
    const wrapper = mount(AppToaster)
    useToastStore().show({ title: 'Falhou', tone: 'danger' })
    await nextTick()

    expect(wrapper.get('li').attributes('role')).toBe('alert')
  })

  it('fecha pelo botão', async () => {
    const wrapper = mount(AppToaster)
    const store = useToastStore()
    store.show({ title: 'Salvo' })
    await nextTick()

    await wrapper.get('button[aria-label="Fechar"]').trigger('click')

    expect(store.toasts).toHaveLength(0)
  })

  it('fecha sozinho após o tempo, e pausa com o mouse em cima', async () => {
    const wrapper = mount(AppToaster)
    const store = useToastStore()
    store.show({ title: 'Salvo' })
    await nextTick()

    await wrapper.get('li').trigger('mouseenter')
    vi.advanceTimersByTime(delay.toast * 2)
    expect(store.toasts).toHaveLength(1)

    await wrapper.get('li').trigger('mouseleave')
    vi.advanceTimersByTime(delay.toast)
    expect(store.toasts).toHaveLength(0)
  })
})
