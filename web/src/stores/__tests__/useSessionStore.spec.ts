import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as authApi from '@/api/auth'
import { ApiError } from '@/api/client'
import type { User } from '@/types/api'

import { useSessionStore } from '../useSessionStore'

vi.mock('@/api/auth')

const user: User = {
  id: '01JB0000000000000000000000',
  name: 'Pessoa Teste',
  email: 'pessoa@example.com',
  email_verified: false,
  created_at: '2026-10-23T12:00:00+00:00',
}

describe('useSessionStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('carrega o usuário logado', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
    const session = useSessionStore()

    await session.load()

    expect(session.status).toBe('authenticated')
    expect(session.user).toEqual(user)
  })

  it('401 em /auth/me: visitante, sem erro', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(new ApiError('Unauthenticated.', 401))
    const session = useSessionStore()

    await session.load()

    expect(session.status).toBe('guest')
    expect(session.user).toBeNull()
  })

  it('outros erros sobem e a sessão continua desconhecida', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(new ApiError('Falha.', 0))
    const session = useSessionStore()

    await expect(session.load()).rejects.toBeInstanceOf(ApiError)
    expect(session.status).toBe('unknown')
  })

  it('chamadas simultâneas fazem uma requisição só', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
    const session = useSessionStore()

    await Promise.all([session.load(), session.load()])

    expect(authApi.fetchCurrentUser).toHaveBeenCalledOnce()
  })

  it('logout limpa a sessão mesmo se a API falhar', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
    vi.mocked(authApi.logout).mockRejectedValue(new ApiError('Falha.', 0))
    const session = useSessionStore()
    await session.load()

    await expect(session.logout()).rejects.toBeInstanceOf(ApiError)
    expect(session.status).toBe('guest')
    expect(session.user).toBeNull()
  })
})
