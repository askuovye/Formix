import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import * as authApi from '@/api/auth'
import { ApiError } from '@/api/client'
import { REPOSITORY_URL } from '@/config/brand'
import type { User } from '@/types/api'

import LandingView from '../LandingView.vue'

vi.mock('@/api/auth')

const user: User = {
  id: '01JB0000000000000000000000',
  name: 'Pessoa Teste',
  email: 'pessoa@example.com',
  email_verified: true,
  created_at: '2026-10-23T12:00:00+00:00',
}

async function mountLanding() {
  const stub = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: LandingView },
      { path: '/entrar', name: 'login', component: stub },
      { path: '/criar-conta', name: 'register', component: stub },
      { path: '/painel/formularios', name: 'forms', component: stub },
      { path: '/termos', name: 'terms', component: stub },
      { path: '/privacidade', name: 'privacy', component: stub },
    ],
  })
  await router.push('/')
  const wrapper = mount(LandingView, { global: { plugins: [router] } })
  await flushPromises()

  return wrapper
}

const links = (wrapper: Awaited<ReturnType<typeof mountLanding>>) =>
  Object.fromEntries(wrapper.findAll('a').map((a) => [a.text(), a.attributes('href')]))

describe('LandingView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  it('visitante: chamadas para criar conta e entrar, âncoras e links do rodapé', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(new ApiError('Não autenticado.', 401, {}))
    const wrapper = await mountLanding()

    // Espaços colapsam no HTML; o que importa é haver um entre as linhas.
    expect(wrapper.get('h1').text().replace(/\s+/g, ' ')).toBe(
      'Formulários que se adaptam ao seu jeito.',
    )
    expect(links(wrapper)).toMatchObject({
      Entrar: '/entrar',
      'Criar conta': '/criar-conta',
      'Começar agora': '/criar-conta',
      'Ver como funciona': '/#como-funciona',
      Recursos: '/#recursos',
      'Termos de uso': '/termos',
      Privacidade: '/privacidade',
      GitHub: REPOSITORY_URL,
    })
    expect(wrapper.text()).not.toContain('Ir para o painel')
    // As âncoras existem na página.
    expect(wrapper.find('#recursos').exists()).toBe(true)
    expect(wrapper.find('#como-funciona').exists()).toBe(true)
    expect(wrapper.findAll('.landing-features__item')).toHaveLength(5)
  })

  it('com sessão, o topo leva ao painel', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
    const wrapper = await mountLanding()

    expect(links(wrapper)['Ir para o painel']).toBe('/painel/formularios')
    expect(wrapper.find('.landing-header').text()).not.toContain('Criar conta')
  })

  it('a ilustração é decorativa e o gráfico tem descrição', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(new ApiError('Não autenticado.', 401, {}))
    const wrapper = await mountLanding()

    expect(wrapper.get('.form-mock').attributes('aria-hidden')).toBe('true')
    expect(wrapper.get('.showcase__chart').attributes('aria-label')).toContain('ilustração')
  })
})
