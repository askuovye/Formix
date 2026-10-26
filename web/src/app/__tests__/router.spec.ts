import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as authApi from '@/api/auth'
import { ApiError } from '@/api/client'
import { useSessionStore } from '@/stores/useSessionStore'
import type { User } from '@/types/api'

import router from '../router'

vi.mock('@/api/auth')

const user: User = {
  id: '01JB0000000000000000000000',
  name: 'Pessoa Teste',
  email: 'pessoa@example.com',
  email_verified: true,
  created_at: '2026-10-23T12:00:00+00:00',
}

/** Sessão nova (Pinia novo) com a resposta de GET /auth/me escolhida. */
function session(me: 'logged-in' | 'guest' | 'offline'): void {
  setActivePinia(createPinia())

  if (me === 'logged-in') {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
  } else {
    const error =
      me === 'guest' ? new ApiError('Unauthenticated.', 401) : new ApiError('Sem conexão.', 0)
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(error)
  }
}

describe('rotas', () => {
  beforeEach(async () => {
    vi.resetAllMocks()
    session('guest')
    // Começa cada teste numa rota pública neutra. Na 1ª vez compila a área pública
    // sob demanda: com cache frio e a máquina ocupada passa dos 10 s padrão.
    await router.replace('/termos')
  }, 30_000)

  // A 1ª navegação ao painel compila o shell inteiro sob demanda: lenta no Windows.
  it('logado: o início leva à lista de formulários', { timeout: 30_000 }, async () => {
    session('logged-in')

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('forms')
  })

  it('sem login: o painel manda para /entrar guardando o destino', async () => {
    await router.push('/painel/configuracoes')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/painel/configuracoes')
  })

  it('logado: as telas de visitante mandam para o painel', async () => {
    session('logged-in')

    await router.push('/entrar')

    expect(router.currentRoute.value.name).toBe('forms')
  })

  it('API fora do ar: trata como visitante sem travar a navegação', async () => {
    session('offline')

    await router.push('/painel')

    expect(router.currentRoute.value.name).toBe('login')
    // Continua "desconhecido": a próxima navegação tenta de novo.
    expect(useSessionStore().status).toBe('unknown')
  })

  it('confirmar email fica aberto sem login', async () => {
    await router.push('/verificar-email?id=1&hash=x&expires=1&signature=y')

    expect(router.currentRoute.value.name).toBe('verify-email')
  })

  it('endereço desconhecido cai na página 404', async () => {
    await router.push('/nao-existe/de-jeito-nenhum')

    expect(router.currentRoute.value.name).toBe('not-found')
    expect(document.title).toBe('Página não encontrada · Formix')
  })

  it('termos e privacidade passam o documento certo para a view', () => {
    const terms = router.resolve('/termos').matched
    const privacy = router.resolve('/privacidade').matched

    expect(terms[terms.length - 1]?.props.default).toEqual({ document: 'terms' })
    expect(privacy[privacy.length - 1]?.props.default).toEqual({ document: 'privacy' })
  })

  it('define o título da aba com o nome da marca', async () => {
    await router.push('/privacidade')

    expect(document.title).toBe('Política de Privacidade · Formix')
  })
})
