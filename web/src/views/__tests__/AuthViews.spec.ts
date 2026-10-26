import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Component } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import * as authApi from '@/api/auth'
import { ApiError } from '@/api/client'
import { useSessionStore } from '@/stores/useSessionStore'
import type { User } from '@/types/api'

import LoginView from '../auth/LoginView.vue'
import RegisterView from '../auth/RegisterView.vue'

vi.mock('@/api/auth')

const user: User = {
  id: '01JB0000000000000000000000',
  name: 'Pessoa Teste',
  email: 'pessoa@example.com',
  email_verified: true,
  created_at: '2026-10-23T12:00:00+00:00',
}

async function mountView(view: Component, url: string) {
  const stub = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/entrar', component: view },
      { path: '/criar-conta', component: view },
      { path: '/:rest(.*)*', component: stub },
    ],
  })
  await router.push(url)
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

  const wrapper = mount(view, {
    attachTo: document.body,
    global: { plugins: [router, [VueQueryPlugin, { queryClient }]] },
  })

  return { wrapper, router }
}

describe('LoginView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  it('valida no cliente sem chamar a API e foca o primeiro erro', async () => {
    const { wrapper } = await mountView(LoginView, '/entrar')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(authApi.login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Informe seu e-mail.')
    expect(document.activeElement).toBe(wrapper.get('input[type="email"]').element)
    wrapper.unmount()
  })

  it('entra, guarda o usuário e volta para o destino pedido', async () => {
    vi.mocked(authApi.login).mockResolvedValue(user)
    const { wrapper, router } = await mountView(LoginView, '/entrar?redirect=/painel/configuracoes')

    await wrapper.get('input[type="email"]').setValue(' Pessoa@Example.com ')
    await wrapper.get('input[type="password"]').setValue('qualquer')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(vi.mocked(authApi.login).mock.calls[0]?.[0]).toEqual({
      email: 'pessoa@example.com',
      password: 'qualquer',
      remember: false,
    })
    expect(useSessionStore().user).toEqual(user)
    expect(router.currentRoute.value.fullPath).toBe('/painel/configuracoes')
    wrapper.unmount()
  })

  it('ignora destino externo no redirect', async () => {
    vi.mocked(authApi.login).mockResolvedValue(user)
    const { wrapper, router } = await mountView(LoginView, '/entrar?redirect=//outro.site')

    await wrapper.get('input[type="email"]').setValue('pessoa@example.com')
    await wrapper.get('input[type="password"]').setValue('qualquer')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/painel')
    wrapper.unmount()
  })

  it('422 da API aparece no campo', async () => {
    vi.mocked(authApi.login).mockRejectedValue(
      new ApiError('Dados inválidos.', 422, { email: ['E-mail ou senha incorretos.'] }),
    )
    const { wrapper } = await mountView(LoginView, '/entrar')

    await wrapper.get('input[type="email"]').setValue('pessoa@example.com')
    await wrapper.get('input[type="password"]').setValue('errada')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const email = wrapper.get('input[type="email"]')
    expect(email.attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('E-mail ou senha incorretos.')
    wrapper.unmount()
  })
})

describe('RegisterView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  it('exige os termos e, depois de criar, mostra a tela de verificar o email', async () => {
    vi.mocked(authApi.register).mockResolvedValue('Conta criada.')
    const { wrapper } = await mountView(RegisterView, '/criar-conta')

    await wrapper.get('input[autocomplete="name"]').setValue('Pessoa Teste')
    await wrapper.get('input[type="email"]').setValue('pessoa@example.com')
    const [password, confirmation] = wrapper.findAll('input[type="password"]')
    await password?.setValue('senhaForte123')
    await confirmation?.setValue('senhaForte123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(authApi.register).not.toHaveBeenCalled()
    expect(wrapper.get('input[type="checkbox"]').attributes('aria-invalid')).toBe('true')

    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(authApi.register).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Verifique seu e-mail')
    expect(wrapper.text()).toContain('pessoa@example.com')
    wrapper.unmount()
  })
})
